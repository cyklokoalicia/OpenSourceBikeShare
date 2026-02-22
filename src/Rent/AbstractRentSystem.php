<?php

namespace BikeShare\Rent;

use BikeShare\Credit\CreditSystemInterface;
use BikeShare\Rent\DTO\RentSystemResult;
use BikeShare\Rent\Enum\RentSystemType;
use BikeShare\Event\BikeRentEvent;
use BikeShare\Event\BikeReturnEvent;
use BikeShare\Event\BikeRevertEvent;
use BikeShare\Notifier\AdminNotifier;
use BikeShare\Repository\BikeRepository;
use BikeShare\Repository\HistoryRepository;
use BikeShare\Repository\NoteRepository;
use BikeShare\Repository\StandRepository;
use BikeShare\Repository\UserRepository;
use BikeShare\Enum\Action;
use Psr\Log\LoggerInterface;
use Symfony\Component\Clock\ClockInterface;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * @phpcs:disable Generic.Files.LineLength
 */
abstract class AbstractRentSystem implements RentSystemInterface
{
    public function __construct(
        protected readonly BikeRepository $bikeRepository,
        protected readonly CreditSystemInterface $creditSystem,
        protected readonly UserRepository $userRepository,
        protected readonly EventDispatcherInterface $eventDispatcher,
        protected readonly AdminNotifier $adminNotifier,
        protected readonly LoggerInterface $logger,
        protected readonly StandRepository $standRepository,
        protected readonly HistoryRepository $historyRepository,
        protected readonly NoteRepository $noteRepository,
        protected readonly RentalCreditCalculator $creditCalculator,
        protected readonly ClockInterface $clock,
        protected readonly TranslatorInterface $translator,
        protected readonly bool $stackWatchEnabled,
        protected readonly bool $isSmsSystemEnabled,
        protected readonly bool $forceStack,
    ) {
    }

    public function rentBike(int $userId, int $bikeId, bool $force = false): RentSystemResult
    {
        $stacktopbike = false;

        $bike = $this->bikeRepository->findItem($bikeId);
        if (empty($bike)) {
            return $this->error(
                $this->translator->trans('Bike {bikeNumber} does not exist.', ['bikeNumber' => $bikeId]),
                'bike.rent.error.not_found',
                ['bikeNumber' => $bikeId]
            );
        }

        if ($force == false) {
            if ($bike['userId'] == $userId) {
                $currentCode = $bike['currentCode'];
                return $this->error(
                    $this->translator->trans(
                        'You have already rented the bike {bikeNumber}. Code is {currentCode}.',
                        ['bikeNumber' => $bikeId, 'currentCode' => $currentCode]
                    ),
                    'bike.rent.error.already_rented_by_current_user',
                    ['bikeNumber' => $bikeId, 'currentCode' => $currentCode]
                );
            } elseif (!empty($bike['userId'])) {
                return $this->error(
                    $this->translator->trans('Bike {bikeNumber} is already rented.', ['bikeNumber' => $bikeId]),
                    'bike.rent.error.already_rented',
                    ['bikeNumber' => $bikeId]
                );
            }

            if (!$this->creditSystem->isEnoughCreditForRent($userId)) {
                $minRequiredCredit = $this->creditSystem->getMinRequiredCredit();

                return $this->error(
                    $this->translator->trans(
                        'You are below required credit {minRequiredCredit}{creditCurrency}. Please, recharge your credit.',
                        [
                            'minRequiredCredit' => $minRequiredCredit,
                            'creditCurrency' => $this->creditSystem->getCreditCurrency()
                        ]
                    ),
                    'bike.rent.error.insufficient_credit',
                    [
                        'minRequiredCredit' => $minRequiredCredit,
                        'creditCurrency' => $this->creditSystem->getCreditCurrency()
                    ]
                );
            }

            $countRented = $this->bikeRepository->countRentedByUser($userId);
            $user = $this->userRepository->findItem($userId);
            $limit = (int)($user['userLimit'] ?? 0);

            if ($countRented >= $limit) {
                if ($limit == 0) {
                    return $this->error(
                        $this->translator->trans('You can not rent any bikes. Contact the admins to lift the ban.'),
                        'bike.rent.error.zero_limit'
                    );
                } else {
                    return $this->error(
                        $this->translator->trans('You can only rent {count} bike at once.', ['count' => $limit]),
                        'bike.rent.error.limit',
                        ['count' => $limit]
                    );
                }
            }

            if ($this->forceStack || $this->stackWatchEnabled) {
                $standid = $bike['currentStand'];
                $stacktopbike = $this->standRepository->findLastReturnedBikeOnStand((int)$standid);

                $stand = $this->standRepository->findItem((int)$standid);
                $serviceTag = $stand['serviceTag'] ?? 0;

                if ($serviceTag != 0 && ($user['privileges'] ?? 0) < 1) {
                    return $this->error(
                        $this->translator->trans('Renting from service stands is not allowed: The bike probably waits for a repair.'),
                        'bike.rent.error.service_stand',
                    );
                }

                if ($this->stackWatchEnabled && $stacktopbike != $bikeId) {
                    $standName = $stand['standName'] ?? '';
                    $userName = $user['userName'] ?? '';
                    $this->notifyAdmins(
                        $this->translator->trans(
                            'Bike {bikeNumber} rented out of stack by {userName}. {stackTopBike} was on the top of the stack at {standName}.',
                            ['bikeNumber' => $bikeId, 'userName' => $userName, 'stackTopBike' => $stacktopbike, 'standName' => $standName]
                        ),
                        false, //bySms
                    );
                }

                if ($this->forceStack && $stacktopbike != $bikeId) {
                    return $this->error(
                        $this->translator->trans(
                            'Bike {bikeNumber} is not rentable now, you have to rent bike {stackTopBike} from this stand.',
                            ['bikeNumber' => $bikeId, 'stackTopBike' => $stacktopbike]
                        ),
                        'bike.rent.error.stack_top_bike',
                        ['bikeNumber' => $bikeId, 'stackTopBike' => $stacktopbike]
                    );
                }
            }
        }

        $currentCode = $bike['currentCode'];
        $note = $bike['notes'] ?? '';

        $newCode = sprintf('%04d', rand(100, 9900)); //do not create a code with more than one leading zero or more than two leading 9s (kind of unusual/unsafe).

        $messageType = $this->getType() === RentSystemType::SMS ? 'text' : 'html';
        $code = 'bike.rent.success';
        $params = ['bikeNumber' => $bikeId, 'currentCode' => $currentCode, 'newCode' => $newCode, 'note' => null];
        $message = $this->translator->trans($code . '.' . $messageType, $params);

        if ($note) {
            $message .= $messageType === 'text' ? "\n" : '<br />';
            $message .= $this->translator->trans('bike.rent.reported_issue.' . $messageType, ['note' => $note]);
            $params['note'] = $note;
        }

        $this->bikeRepository->assignToUser($bikeId, $userId, $newCode);

        $this->historyRepository->addItem(
            $userId,
            $bikeId,
            $force ? Action::FORCE_RENT : Action::RENT,
            $newCode,
        );

        $this->eventDispatcher->dispatch(
            new BikeRentEvent($bikeId, $userId, $force)
        );

        return $this->success($message, $code, $params);
    }

    public function returnBike(
        int $userId,
        int $bikeId,
        string $standName,
        ?string $note = null,
        bool $force = false
    ): RentSystemResult
    {
        $stand = strtoupper($standName);

        $standData = $this->standRepository->findItemByName($stand);
        if (empty($standData)) {
            return $this->error(
                $this->translator->trans(
                    "Stand name '{standName}' does not exist. Stands are marked by CAPITALLETTERS.",
                    ['standName' => $stand]
                ),
                'bike.return.error.stand_not_found',
                ['standName' => $stand]
            );
        }

        $standId = (int)$standData['standId'];

        $bike = $this->bikeRepository->findItem($bikeId);

        if ($force == false) {
            if (empty($bike) || (int)($bike['userId'] ?? 0) !== $userId) {
                return $this->error(
                    $this->translator->trans('You currently have no rented bikes.'),
                    'bike.return.error.no_rented_bikes'
                );
            }
        }

        $currentCode = $bike['currentCode'] ?? '';

        $this->bikeRepository->returnToStand($bikeId, $standId, $force ? null : $userId);

        if ($note) {
            $this->addNote($userId, $bikeId, $note);
        } else {
            $note = $this->noteRepository->findBikeNote($bikeId)[0]['note'] ?? '';
        }

        $messageType = $this->getType() === RentSystemType::SMS ? 'text' : 'html';
        $code = 'bike.return.success';
        $message = $this->translator->trans(
            'bike.return.success.' . $messageType,
            ['bikeNumber' => $bikeId, 'standName' => $stand, 'currentCode' => $currentCode, 'note' => null]
        );
        $params = ['bikeNumber' => $bikeId, 'standName' => $stand, 'currentCode' => $currentCode];
        if ($note) {
            $message .= $messageType === 'text' ? "\n" : '<br />';
            $message .= $this->translator->trans('You have also reported this problem: {note}.', ['note' => $note]);
            $params['note'] = $note;
        }

        if ($force == false) {
            $creditchange = $this->creditCalculator->calculateAndApply($bikeId, $userId);
            if ($this->creditSystem->isEnabled() && $creditchange) {
                $message .= $messageType === 'text' ? "\n" : '<br />';
                $message .= $this->translator->trans(
                    'Credit change: -{creditChange}{creditCurrency}.',
                    [
                        'creditChange' => $creditchange,
                        'creditCurrency' => $this->creditSystem->getCreditCurrency()
                    ]
                );
                $params['creditChange'] = $creditchange;
                $params['creditCurrency'] = $this->creditSystem->getCreditCurrency();
            }
        }

        $this->historyRepository->addItem(
            $userId,
            $bikeId,
            $force ? Action::FORCE_RETURN : Action::RETURN,
            (string)$standId,
        );

        $this->eventDispatcher->dispatch(
            new BikeReturnEvent($bikeId, $standName, $userId, $force)
        );

        return $this->success($message, $code, $params);
    }

    public function revertBike(int $userId, int $bikeId): RentSystemResult
    {
        $bike = $this->bikeRepository->findItem($bikeId);
        $previousOwnerId = !empty($bike) ? ((int)($bike['userId'] ?? 0) ?: null) : null;
        if ($previousOwnerId === null) {
            return $this->error(
                $this->translator->trans(
                    'Bicycle {bikeNumber} is not rented right now. Revert not successful!',
                    ['bikeNumber' => $bikeId]
                ),
                'bike.revert.error.not_rented',
                ['bikeNumber' => $bikeId]
            );
        }

        $lastReturn = $this->historyRepository->findLastReturnStand($bikeId);
        $code = $this->historyRepository->findLastRentCode($bikeId);

        if ($lastReturn && $code) {
            $standId = $lastReturn['standId'];
            $stand = $lastReturn['standName'];

            $this->bikeRepository->revertToStand($bikeId, $standId, $code);

            $this->historyRepository->addItem(
                $userId,
                $bikeId,
                Action::REVERT,
                sprintf('%s|%s', $standId, $code),
            );
            $this->historyRepository->addItem(
                $userId,
                $bikeId,
                Action::RENT,
                $code,
            );
            $this->historyRepository->addItem(
                $userId,
                $bikeId,
                Action::RETURN,
                (string)$standId,
            );

            $this->eventDispatcher->dispatch(
                new BikeRevertEvent($bikeId, $userId, $previousOwnerId)
            );
            $messageType = $this->getType() === RentSystemType::SMS ? 'text' : 'html';

            return $this->success(
                $this->translator->trans(
                    'bike.revert.success.' . $messageType,
                    ['bikeNumber' => $bikeId, 'standName' => $stand, 'code' => $code]
                ),
                'bike.revert.success',
                ['bikeNumber' => $bikeId, 'standName' => $stand, 'code' => $code]
            );
        } else {
            return $this->error(
                $this->translator->trans(
                    'No last stand or code for bicycle {bikeNumber} found. Revert not successful!',
                    ['bikeNumber' => $bikeId]
                ),
                'bike.revert.error.no_stand_or_code',
                ['bikeNumber' => $bikeId]
            );
        }
    }

    abstract public static function getType(): RentSystemType;

    protected function success(string $message, string $code, array $params = []): RentSystemResult
    {
        return $this->createResult(false, $message, $code, $params);
    }

    protected function error(string $message, string $code, array $params = []): RentSystemResult
    {
        return $this->createResult(true, $message, $code, $params);
    }

    protected function createResult(bool $error, string $message, string $code, array $params = []): RentSystemResult
    {
        return new RentSystemResult($error, $this->normalizeMessage($message), $code, static::getType(), $params);
    }

    protected function normalizeMessage(string $message): string
    {
        return $message;
    }

    private function notifyAdmins(string $message, bool $bySms = true)
    {
        $this->adminNotifier->notify($message, $bySms);
    }

    private function addNote($userId, $bikeNum, $message)
    {
        $userNote = trim($message);

        $user = $this->userRepository->findItem($userId);
        $userName = $user['userName'] ?? '';
        $phone = $user['number'] ?? '';

        $bikeUsage = $this->bikeRepository->findBikeCurrentUsage($bikeNum);
        $standName = $bikeUsage['standName'] ?? null;
        if ($standName != null) {
            $bikeStatus = $this->translator->trans('at {standName}', ['standName' => $standName]);
        } else {
            $bikeStatus = $this->translator->trans('used by {userName} +{phone}', ['userName' => $userName, 'phone' => $phone]);
        }

        $noteid = $this->noteRepository->addNoteToBike($bikeNum, $userId, $userNote);
        $this->notifyAdmins(
            $this->translator->trans(
                'Note #{noteId}: b.{bikeNumber} ({bikeStatus}) by {userName}/{phone}:{userNote}',
                [
                    'noteId' => $noteid,
                    'bikeNumber' => $bikeNum,
                    'bikeStatus' => $bikeStatus,
                    'userName' => $userName,
                    'phone' => $phone,
                    'userNote' => $userNote
                ]
            )
        );
    }
}
