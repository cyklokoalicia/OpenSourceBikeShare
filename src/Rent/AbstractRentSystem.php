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
use Symfony\Component\Translation\TranslatableMessage;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use Symfony\Contracts\Translation\TranslatableInterface;

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
        protected readonly bool $stackWatchEnabled,
        protected readonly bool $isSmsSystemEnabled,
        protected readonly bool $forceStack,
    ) {
    }

    public function rentBike(int $userId, int $bikeId, bool $force = false): RentSystemResult
    {
        $bike = $this->bikeRepository->findItem($bikeId);
        if (empty($bike)) {
            return $this->error('bike.rent.error.not_found', ['bikeNumber' => $bikeId]);
        }

        if ($force === false) {
            if ($bike['userId'] == $userId) {
                return $this->error(
                    'bike.rent.error.already_rented_by_current_user',
                    ['bikeNumber' => $bikeId, 'currentCode' => $bike['currentCode']]
                );
            } elseif (!empty($bike['userId'])) {
                return $this->error('bike.rent.error.already_rented', ['bikeNumber' => $bikeId]);
            }

            if (!$this->creditSystem->isEnoughCreditForRent($userId)) {
                return $this->error(
                    'bike.rent.error.insufficient_credit',
                    [
                        'minRequiredCredit' => $this->creditSystem->getMinRequiredCredit(),
                        'creditCurrency' => $this->creditSystem->getCreditCurrency(),
                    ]
                );
            }

            $rentedBikedByUser = $this->bikeRepository->findRentedBikesByUserId($userId);
            $countRented = count($rentedBikedByUser);

            $user = $this->userRepository->findItem($userId);
            $limit = $user['userLimit'];

            if ($countRented >= $limit) {
                if ($limit == 0) {
                    return $this->error('bike.rent.error.zero_limit');
                } else {
                    return $this->error('bike.rent.error.limit', ['count' => $limit]);
                }
            }

            if ($this->forceStack || $this->stackWatchEnabled) {
                $standid = $bike['currentStand'];
                $stackTopBike = $this->standRepository->findLastReturnedBikeOnStand((int)$standid);

                $stand = $this->standRepository->findItem((int)$standid);
                $serviceTag = $stand['serviceTag'] ?? 0;

                if ($serviceTag != 0 && ($user['privileges'] ?? 0) < 1) {
                    return $this->error('bike.rent.error.service_stand');
                }

                if ($this->stackWatchEnabled && $stackTopBike != $bikeId) {
                    $this->notifyAdmins(
                        new TranslatableMessage(
                            'bike.rent.admin.stack_watch',
                            [
                                'bikeNumber' => $bikeId,
                                'userName' => $user['userName'] ?? '',
                                'stackTopBike' => $stackTopBike,
                                'standName' => $stand['standName'] ?? '',
                            ]
                        ),
                        false,
                    );
                }

                if ($this->forceStack && $stackTopBike != $bikeId) {
                    return $this->error(
                        'bike.rent.error.stack_top_bike',
                        ['bikeNumber' => $bikeId, 'stackTopBike' => $stackTopBike]
                    );
                }
            }
        }

        $currentCode = $bike['currentCode'];
        $note = $bike['notes'];

        // Avoid more than one leading zero or more than two leading 9s (unusual/unsafe).
        $newCode = sprintf('%04d', rand(100, 9900));

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

        return $this->success(
            'bike.rent.success',
            [
                'bikeNumber' => $bikeId,
                'currentCode' => $currentCode,
                'newCode' => $newCode,
                'hasNote' => $note ? 'true' : 'false',
                'note' => $note ?? '',
            ]
        );
    }

    public function returnBike(
        int $userId,
        int $bikeId,
        string $standName,
        ?string $note = null,
        bool $force = false
    ): RentSystemResult {
        $stand = strtoupper($standName);

        $standData = $this->standRepository->findItemByName($stand);
        if (empty($standData)) {
            return $this->error('bike.return.error.stand_not_found', ['standName' => $stand]);
        }

        $standId = (int)$standData['standId'];

        $bike = $this->bikeRepository->findItem($bikeId);

        if ($force === false) {
            if (empty($bike) || $bike['userId'] != $userId) {
                return $this->error('bike.return.error.no_rented_bikes');
            }
        }

        $currentCode = $bike['currentCode'];

        $this->bikeRepository->returnToStand($bikeId, $standId, $force ? null : $userId);

        if ($note) {
            $this->addNote($userId, $bikeId, $note);
        } else {
            $note = $this->noteRepository->findBikeNote($bikeId)[0]['note'] ?? '';
        }

        $creditChange = null;
        if ($force === false) {
            $creditChange = $this->creditCalculator->calculateAndApply($bikeId, $userId);
        }
        $hasCreditChange = $force === false && $this->creditSystem->isEnabled() && $creditChange;

        $this->historyRepository->addItem(
            $userId,
            $bikeId,
            $force ? Action::FORCE_RETURN : Action::RETURN,
            (string)$standId,
        );

        $this->eventDispatcher->dispatch(
            new BikeReturnEvent($bikeId, $standName, $userId, $force)
        );

        return $this->success(
            'bike.return.success',
            [
                'bikeNumber' => $bikeId,
                'standName' => $stand,
                'currentCode' => $currentCode,
                'hasNote' => $note !== '' ? 'true' : 'false',
                'note' => $note,
                'hasCreditChange' => $hasCreditChange ? 'true' : 'false',
                'creditChange' => $creditChange ?? 0,
                'creditCurrency' => $this->creditSystem->getCreditCurrency(),
            ]
        );
    }

    public function revertBike(int $userId, int $bikeId): RentSystemResult
    {
        $bike = $this->bikeRepository->findItem($bikeId);
        $previousOwnerId = !empty($bike) ? ((int)($bike['userId'] ?? 0) ?: null) : null;
        if ($previousOwnerId === null) {
            return $this->error('bike.revert.error.not_rented', ['bikeNumber' => $bikeId]);
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

            return $this->success(
                'bike.revert.success',
                ['bikeNumber' => $bikeId, 'standName' => $stand, 'code' => $code]
            );
        }

        return $this->error('bike.revert.error.no_stand_or_code', ['bikeNumber' => $bikeId]);
    }

    abstract public static function getType(): RentSystemType;

    protected function success(string $code, array $params = []): RentSystemResult
    {
        return new RentSystemResult(false, $code, static::getType(), $params);
    }

    protected function error(string $code, array $params = []): RentSystemResult
    {
        return new RentSystemResult(true, $code, static::getType(), $params);
    }

    private function notifyAdmins(TranslatableInterface $message, bool $bySms = true): void
    {
        $this->adminNotifier->notify($message, $bySms);
    }

    private function addNote(int $userId, int $bikeNum, string $message): void
    {
        $userNote = trim($message);

        $user = $this->userRepository->findItem($userId);
        $userName = $user['userName'] ?? '';
        $phone = $user['number'] ?? '';

        $bikeUsage = $this->bikeRepository->findBikeCurrentUsage($bikeNum);
        $standName = $bikeUsage['standName'] ?? null;
        if ($standName !== null) {
            $bikeStatus = new TranslatableMessage('bike.status.at_stand', ['standName' => $standName]);
        } else {
            $bikeStatus = new TranslatableMessage(
                'bike.status.in_use',
                ['userName' => $userName, 'phone' => $phone]
            );
        }

        $noteId = $this->noteRepository->addNoteToBike($bikeNum, $userId, $userNote);
        $this->notifyAdmins(
            new TranslatableMessage(
                'bike.note.admin.notification',
                [
                    'noteId' => $noteId,
                    'bikeNumber' => $bikeNum,
                    'bikeStatus' => $bikeStatus,
                    'userName' => $userName,
                    'phone' => $phone,
                    'userNote' => $userNote,
                ]
            )
        );
    }
}
