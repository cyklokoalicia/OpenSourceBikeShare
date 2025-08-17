<?php

namespace BikeShare\Rent;

use BikeShare\Credit\CreditSystemInterface;
use BikeShare\Db\DbInterface;
use BikeShare\Event\BikeRentEvent;
use BikeShare\Event\BikeReturnEvent;
use BikeShare\Event\BikeRevertEvent;
use BikeShare\Notifier\AdminNotifier;
use BikeShare\Repository\BikeRepository;
use BikeShare\Repository\StandRepository;
use BikeShare\User\User;
use BikeShare\Enum\Action;
use Psr\Log\LoggerInterface;
use Symfony\Component\Clock\ClockInterface;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * @phpcs:disable PSR12.Classes.PropertyDeclaration
 * @phpcs:disable Generic.Files.LineLength
 */
abstract class AbstractRentSystem implements RentSystemInterface
{
    protected const ERROR = 1;

    public function __construct(
        protected readonly BikeRepository $bikeRepository,
        protected readonly DbInterface $db,
        protected readonly CreditSystemInterface $creditSystem,
        protected readonly User $user,
        protected readonly EventDispatcherInterface $eventDispatcher,
        protected readonly AdminNotifier $adminNotifier,
        protected readonly LoggerInterface $logger,
        protected readonly StandRepository $standRepository,
        protected readonly ClockInterface $clock,
        protected readonly TranslatorInterface $translator,
        protected array $watchesConfig,
        protected readonly bool $isSmsSystemEnabled,
        protected readonly bool $forceStack,
    ) {
    }

    public function rentBike($userId, $bikeId, $force = false)
    {
        $stacktopbike = false;
        $userId = intval($userId);
        $bikeNum = intval($bikeId);

        $bike = $this->bikeRepository->findItem($bikeNum);
        if (empty($bike)) {
            return $this->response(
                $this->translator->trans('Bike {bikeNumber} does not exist.', ['bikeNumber' => $bikeNum]),
                self::ERROR,
                'Bike {bikeNumber} does not exist.',
                ['bikeNumber' => $bikeNum]
            );
        }

        if ($force == false) {
            if ($bike['userId'] == $userId) {
                $result = $this->db->query("SELECT currentCode FROM bikes WHERE bikeNum = :bikeNum", ['bikeNum' => $bikeNum])->fetchAssoc();
                return $this->response(
                    $this->translator->trans(
                        'You have already rented the bike {bikeNumber}. Code is {currentCode}.',
                        ['bikeNumber' => $bikeNum, 'currentCode' => str_pad($result['currentCode'], 4, '0', STR_PAD_LEFT)]
                    ),
                    self::ERROR,
                    'You have already rented the bike {bikeNumber}. Code is {currentCode}.',
                    ['bikeNumber' => $bikeNum, 'currentCode' => str_pad($result['currentCode'], 4, '0', STR_PAD_LEFT)]
                );
            } elseif (!empty($bike['userId'])) {
                return $this->response(
                    $this->translator->trans('Bike {bikeNumber} is already rented.', ['bikeNumber' => $bikeNum]),
                    self::ERROR,
                    'Bike {bikeNumber} is already rented.',
                    ['bikeNumber' => $bikeNum]
                );
            }

            if (!$this->creditSystem->isEnoughCreditForRent($userId)) {
                $minRequiredCredit = $this->creditSystem->getMinRequiredCredit();

                return $this->response(
                    $this->translator->trans(
                        'You are below required credit {minRequiredCredit}{creditCurrency}. Please, recharge your credit.',
                        [
                            'minRequiredCredit' => $minRequiredCredit,
                            'creditCurrency' => $this->creditSystem->getCreditCurrency()
                        ]
                    ),
                    self::ERROR,
                    'You are below required credit {minRequiredCredit}{creditCurrency}. Please, recharge your credit.',
                    [
                        'minRequiredCredit' => $minRequiredCredit,
                        'creditCurrency' => $this->creditSystem->getCreditCurrency()
                    ]
                );
            }

            $result = $this->db->query("SELECT count(*) as countRented FROM bikes where currentUser=$userId");
            $row = $result->fetchAssoc();
            $countRented = $row['countRented'];

            $result = $this->db->query("SELECT userLimit FROM users where userId = :userId", ['userId' => $userId]);
            $row = $result->fetchAssoc();
            $limit = $row['userLimit'];

            if ($countRented >= $limit) {
                if ($limit == 0) {
                    return $this->response(
                        $this->translator->trans('You can not rent any bikes. Contact the admins to lift the ban.'),
                        self::ERROR,
                        'You can not rent any bikes. Contact the admins to lift the ban.'
                    );
                } else {
                    return $this->response(
                        $this->translator->trans('You can only rent {count} bike at once.', ['count' => $limit]),
                        self::ERROR,
                        'You can only rent {count} bike at once.',
                        ['count' => $limit]
                    );
                }
            }

            if ($this->forceStack || $this->watchesConfig['stack']) {
                $result = $this->db->query("SELECT currentStand FROM bikes WHERE bikeNum='$bikeId'");
                $row = $result->fetchAssoc();
                $standid = $row['currentStand'];
                $stacktopbike = $this->standRepository->findLastReturnedBikeOnStand((int)$standid);

                $result = $this->db->query("SELECT serviceTag FROM stands WHERE standId='$standid'");
                $row = $result->fetchAssoc();
                $serviceTag = $row['serviceTag'];

                if ($serviceTag != 0) {
                    return $this->response(
                        $this->translator->trans('Renting from service stands is not allowed: The bike probably waits for a repair.'),
                        self::ERROR,
                        'Renting from service stands is not allowed: The bike probably waits for a repair.'
                    );
                }

                if ($this->watchesConfig['stack'] && $stacktopbike != $bikeId) {
                    $result = $this->db->query("SELECT standName FROM stands WHERE standId='$standid'");
                    $row = $result->fetchAssoc();
                    $stand = $row['standName'];
                    $userName = $this->user->findUserName($userId);
                    $this->notifyAdmins(
                        $this->translator->trans(
                            'Bike {bikeNumber} rented out of stack by {userName}. {stackTopBike} was on the top of the stack at {standName}.',
                            ['bikeNumber' => $bikeId, 'userName' => $userName, 'stackTopBike' => $stacktopbike, 'standName' => $stand]
                        ),
                        false, //bySms
                    );
                }

                if ($this->forceStack && $stacktopbike != $bikeId) {
                    return $this->response(
                        $this->translator->trans(
                            'Bike {bikeNumber} is not rentable now, you have to rent bike {stackTopBike} from this stand.',
                            ['bikeNumber' => $bikeId, 'stackTopBike' => $stacktopbike]
                        ),
                        self::ERROR,
                        'Bike {bikeNumber} is not rentable now, you have to rent bike {stackTopBike} from this stand.',
                        ['bikeNumber' => $bikeId, 'stackTopBike' => $stacktopbike]
                    );
                }
            }
        }

        $result = $this->db->query("SELECT currentCode FROM bikes WHERE bikeNum = :bikeNum", ['bikeNum' => $bikeNum]);
        $row = $result->fetchAssoc();
        $currentCode = sprintf('%04d', $row['currentCode']);
        $result = $this->db->query("SELECT note FROM notes WHERE bikeNum='$bikeNum' AND deleted IS NULL ORDER BY time DESC");
        $note = '';
        while ($row = $result->fetchAssoc()) {
            $note .= $row['note'] . '; ';
        }

        $note = substr($note, 0, strlen($note) - 2); // remove the last two chars - comma and space

        $newCode = sprintf('%04d', rand(100, 9900)); //do not create a code with more than one leading zero or more than two leading 9s (kind of unusual/unsafe).

        $messageType = $this->getType() === 'sms' ? 'text' : 'html';
        $code = 'bike.rent.success.' . $messageType;
        $params = ['bikeNumber' => $bikeNum, 'currentCode' => $currentCode, 'newCode' => $newCode];
        $message = $this->translator->trans($code, $params);

        if ($note) {
            $message .= $messageType === 'text' ? "\n" : '<br />';
            $message .= $this->translator->trans('bike.rent.reported_issue.' . $messageType, ['note' => $note]);
            $params['note'] = $note;
        }

        $result = $this->db->query("UPDATE bikes SET currentUser=$userId,currentCode=$newCode,currentStand=NULL WHERE bikeNum=$bikeNum");
        if ($force) {
            //$this->response($this->translator->trans('System override: Your rented bike {bikeNumber} has been rented by admin.', ['bikeNumber' => $bikeNum]));
        }
        $result = $this->db->query(
            "INSERT INTO history SET userId = :userId, bikeNum = :bikeNum, action = :action, parameter = :newCode, time = :time",
            [
                'userId' => $userId,
                'bikeNum' => $bikeNum,
                'action' => $force ? Action::FORCE_RENT->value : Action::RENT->value,
                'newCode' => $newCode,
                'time' => $this->clock->now()->format('Y-m-d H:i:s'),
            ]
        );

        $this->eventDispatcher->dispatch(
            new BikeRentEvent($bikeNum, $userId, $force)
        );

        return $this->response($message, 0, $code, $params);
    }

    public function returnBike($userId, $bikeId, $standName, $note = '', $force = false)
    {
        $userId = intval($userId);
        $bikeNum = intval($bikeId);
        $stand = strtoupper($standName);

        $result = $this->db->query("SELECT standId FROM stands WHERE standName='$stand'");
        if (!$result->rowCount()) {
            return $this->response(
                $this->translator->trans(
                    'Stand name \'{standName}\' does not exist. Stands are marked by CAPITALLETTERS.',
                    ['standName' => $stand]
                ),
                self::ERROR,
                'Stand name \'{standName}\' does not exist. Stands are marked by CAPITALLETTERS.',
                ['standName' => $stand]
            );
        }

        $row = $result->fetchAssoc();
        $standId = $row["standId"];

        if ($force == false) {
            $result = $this->db->query("SELECT bikeNum FROM bikes WHERE currentUser=$userId AND bikeNum=$bikeId ORDER BY bikeNum");
            $bikenumber = $result->rowCount();

            if ($bikenumber == 0) {
                return $this->response(
                    $this->translator->trans('You currently have no rented bikes.'),
                    self::ERROR,
                    'You currently have no rented bikes.'
                );
            }
        }

        if ($force == false) {
            $result = $this->db->query("SELECT currentCode FROM bikes WHERE currentUser=$userId AND bikeNum=$bikeNum");
        } else {
            $result = $this->db->query("SELECT currentCode FROM bikes WHERE bikeNum=$bikeNum");
        }

        $row = $result->fetchAssoc();
        $currentCode = sprintf('%04d', $row['currentCode']);

        if ($force == false) {
            $result = $this->db->query("UPDATE bikes SET currentUser=NULL,currentStand=$standId WHERE bikeNum=$bikeNum and currentUser=$userId");
        } else {
            $result = $this->db->query("UPDATE bikes SET currentUser=NULL,currentStand=$standId WHERE bikeNum=$bikeNum");
        }

        if ($note) {
            $this->addNote($userId, $bikeNum, $note);
        } else {
            $result = $this->db->query("SELECT note FROM notes WHERE bikeNum=$bikeNum AND deleted IS NULL ORDER BY time DESC LIMIT 1");
            $row = $result->fetchAssoc();
            $note = $row["note"] ?? '';
        }

        $messageType = $this->getType() === 'sms' ? 'text' : 'html';
        $message = $this->translator->trans(
            'bike.return.success.' . $messageType,
            ['bikeNumber' => $bikeNum, 'standName' => $stand, 'currentCode' => $currentCode]
        );
        if ($note) {
            $message .= $messageType === 'text' ? "\n" : '<br />';
            $message .= $this->translator->trans('You have also reported this problem: {note}.', ['note' => $note]);
        }

        if ($force == false) {
            $creditchange = $this->changecreditendrental($bikeNum, $userId);
            if ($this->creditSystem->isEnabled() && $creditchange) {
                $message .= $messageType === 'text' ? "\n" : '<br />';
                $message .= $this->translator->trans(
                    'Credit change: -{creditChange}{creditCurrency}.',
                    [
                        'creditChange' => $creditchange,
                        'creditCurrency' => $this->creditSystem->getCreditCurrency()
                    ]
                );
            }
        }
        $result = $this->db->query(
            "INSERT INTO history SET userId = :userId, bikeNum = :bikeNum, action = :action, parameter = :standId, time = :time",
            [
                'userId' => $userId,
                'bikeNum' => $bikeNum,
                'action' => $force ? Action::FORCE_RETURN->value : Action::RETURN->value,
                'standId' => $standId,
                'time' => $this->clock->now()->format('Y-m-d H:i:s'),
            ]
        );

        $this->eventDispatcher->dispatch(
            new BikeReturnEvent($bikeNum, $standName, $userId, $force)
        );

        return $this->response($message);
    }

    public function revertBike($userId, $bikeId)
    {
        $userId = intval($userId);
        $bikeId = intval($bikeId);

        $standId = 0;
        $result = $this->db->query("SELECT currentUser FROM bikes WHERE bikeNum=$bikeId AND currentUser IS NOT NULL");
        if (!$result->rowCount()) {
            return $this->response(
                $this->translator->trans(
                    'Bicycle {bikeNumber} is not rented right now. Revert not successful!',
                    ['bikeNumber' => $bikeId]
                ),
                self::ERROR,
                'Bicycle {bikeNumber} is not rented right now. Revert not successful!',
                ['bikeNumber' => $bikeId]
            );
        } else {
            $row = $result->fetchAssoc();
            $previousOwnerId = $row['currentUser'];
        }

        $result = $this->db->query(
            "SELECT parameter,standName
             FROM stands
             LEFT JOIN history ON stands.standId=parameter
             WHERE bikeNum = :bikeNum
              AND action IN (:returnAction, :forceReturnAction)
             ORDER BY time DESC
             LIMIT 1",
            [
                'bikeNum' => $bikeId,
                'returnAction' => Action::RETURN->value,
                'forceReturnAction' => Action::FORCE_RETURN->value,
            ]
        );
        if ($result->rowCount() === 1) {
            $row = $result->fetchAssoc();
            $standId = $row['parameter'];
            $stand = $row['standName'];
        }

        $result = $this->db->query(
            "SELECT parameter
             FROM history
             WHERE bikeNum = :bikeNum
               AND action IN (:rentAction, :forceRentAction)
             ORDER BY time DESC
             LIMIT 1",
            [
                'bikeNum' => $bikeId,
                'rentAction' => Action::RENT->value,
                'forceRentAction' => Action::FORCE_RENT->value,
            ]
        );
        if ($result->rowCount() == 1) {
            $row = $result->fetchAssoc();
            $code = str_pad($row['parameter'], 4, '0', STR_PAD_LEFT);
        }

        if ($standId && $code) {
            $this->db->query("UPDATE bikes SET currentUser=NULL,currentStand=$standId,currentCode=$code WHERE bikeNum=$bikeId");

            $this->db->query(
                "INSERT INTO history SET userId = :userId, bikeNum = :bikeNum, action = :action, parameter = :parameter, time = :time",
                [
                    'userId' => $userId,
                    'bikeNum' => $bikeId,
                    'action' => Action::REVERT->value,
                    'parameter' => "$standId|$code",
                    'time' => $this->clock->now()->format('Y-m-d H:i:s'),
                ]
            );
            $this->db->query(
                "INSERT INTO history SET userId = :userId, bikeNum = :bikeNum, action = :action, parameter = :code, time = :time",
                [
                    'userId' => $userId,
                    'bikeNum' => $bikeId,
                    'action' => Action::RENT->value,
                    'code' => $code,
                    'time' => $this->clock->now()->format('Y-m-d H:i:s'),
                ]
            );
            $this->db->query(
                "INSERT INTO history SET userId = :userId, bikeNum = :bikeNum, action = :action, parameter = :standId, time = :time",
                [
                    'userId' => $userId,
                    'bikeNum' => $bikeId,
                    'action' => Action::RETURN->value,
                    'standId' => $standId,
                    'time' => $this->clock->now()->format('Y-m-d H:i:s'),
                ]
            );

            $this->eventDispatcher->dispatch(
                new BikeRevertEvent($bikeId, $userId, $previousOwnerId)
            );
            $messageType = $this->getType() === 'sms' ? 'text' : 'html';

            return $this->response(
                $this->translator->trans(
                    'bike.revert.success.' . $messageType,
                    ['bikeNumber' => $bikeId, 'standName' => $stand, 'code' => $code]
                ),
                0,
                'bike.revert.success.' . $messageType,
                ['bikeNumber' => $bikeId, 'standName' => $stand, 'code' => $code]
            );
        } else {
            return $this->response(
                $this->translator->trans(
                    'No last stand or code for bicycle {bikeNumber} found. Revert not successful!',
                    ['bikeNumber' => $bikeId]
                ),
                self::ERROR,
                'No last stand or code for bicycle {bikeNumber} found. Revert not successful!',
                ['bikeNumber' => $bikeId]
            );
        }
    }

    abstract public static function getType(): string;

    protected function response($message, $error = 0, string $code = '', array $params = [])
    {
        return [
            'error' => $error,
            'message' => $message,
            'code' => $code,
            'params' => $params,
        ];
    }

    private function notifyAdmins(string $message, bool $bySms = true)
    {
        $this->adminNotifier->notify($message, $bySms);
    }

    private function addnote($userId, $bikeNum, $message)
    {
        $userNote = $this->db->escape(trim($message));

        $userName = $this->user->findUserName($userId);
        $phone = $this->user->findPhoneNumber($userId);
        $result = $this->db->query("SELECT stands.standName FROM bikes LEFT JOIN users on bikes.currentUser=users.userID LEFT JOIN stands on bikes.currentStand=stands.standId WHERE bikeNum=$bikeNum");
        $row = $result->fetchAssoc();
        $standName = $row['standName'];
        if ($standName != null) {
            $bikeStatus = $this->translator->trans('at {standName}', ['standName' => $standName]);
        } else {
            $bikeStatus = $this->translator->trans('used by {userName} +{phone}', ['userName' => $userName, 'phone' => $phone]);
        }
        $this->db->query("INSERT INTO notes SET bikeNum='$bikeNum',userId='$userId',note='$userNote'");
        $noteid = $this->db->getLastInsertId();
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

    // subtract credit for rental
    private function changecreditendrental($bike, $userid): ?float
    {
        if ($this->creditSystem->isEnabled() === false) {
            return null;
        }

        $userCredit = $this->creditSystem->getUserCredit($userid);

        $result = $this->db->query(
            "SELECT time FROM history WHERE bikeNum = :bikeNum AND userId = :userId AND action IN (:rentAction, :forceRentAction) ORDER BY time DESC LIMIT 1",
            [
                'bikeNum' => $bike,
                'userId' => $userid,
                'rentAction' => Action::RENT->value,
                'forceRentAction' => Action::FORCE_RENT->value,
            ]
        );
        if ($result->rowCount() == 1) {
            $row = $result->fetchAssoc();
            $startTime = new \DateTimeImmutable($row['time']);
            $endTime = $this->clock->now();
            $timeDiff = $endTime->getTimestamp() - $startTime->getTimestamp();
            $creditchange = 0;
            $changelog = '';

            // if the bike is returned and rented again within 10 minutes, a user will not have new free time.
            $oldRetrun = $this->db->query(
                "SELECT time FROM history WHERE bikeNum = :bikeNum AND userId = :userId AND action IN (:returnAction, :forceReturnAction) ORDER BY time DESC LIMIT 1",
                [
                    'bikeNum' => $bike,
                    'userId' => $userid,
                    'returnAction' => Action::RETURN->value,
                    'forceReturnAction' => Action::FORCE_RETURN->value,
                ]
            );
            if ($oldRetrun->rowCount() == 1) {
                $oldRow = $oldRetrun->fetchAssoc();
                $returnTime = new \DateTimeImmutable($oldRow["time"]);
                if (($startTime->getTimestamp() - $returnTime->getTimestamp()) < 10 * 60 && $timeDiff > 5 * 60) {
                    $creditchange = $creditchange + $this->creditSystem->getRentalFee();
                    $changelog .= 'rerent-' . $this->creditSystem->getRentalFee() . ';';
                }
            }

            if ($timeDiff > $this->watchesConfig['freetime'] * 60) {
                $creditchange += $this->creditSystem->getRentalFee();
                $changelog .= 'overfree-' . $this->creditSystem->getRentalFee() . ';';
            }

            if ($this->watchesConfig['freetime'] == 0) {
                $this->watchesConfig['freetime'] = 1;
            }

            // for further calculations
            if ($this->creditSystem->getPriceCycle() && $timeDiff > $this->watchesConfig['freetime'] * 60 * 2) {
                // after first paid period, i.e. freetime*2; if pricecycle enabled
                $temptimediff = $timeDiff - ($this->watchesConfig['freetime'] * 60 * 2);
                if ($this->creditSystem->getPriceCycle() == 1) { // flat price per cycle
                    $cycles = ceil($temptimediff / ($this->watchesConfig['flatpricecycle'] * 60));
                    $creditchange += $this->creditSystem->getRentalFee() * $cycles;
                    $changelog .= 'flat-' . $this->creditSystem->getRentalFee() * $cycles . ';';
                } elseif ($this->creditSystem->getPriceCycle() == 2) { // double price per cycle
                    $cycles = ceil($temptimediff / ($this->watchesConfig['doublepricecycle'] * 60));
                    $tempcreditrent = $this->creditSystem->getRentalFee();
                    for ($i = 1; $i <= $cycles; $i++) {
                        $multiplier = $i;
                        if ($multiplier > $this->watchesConfig['doublepricecyclecap']) {
                            $multiplier = $this->watchesConfig['doublepricecyclecap'];
                        }

                        // exception for rent=1, otherwise square won't work:
                        if ($tempcreditrent == 1) {
                            $tempcreditrent = 2;
                        }

                        $creditchange += pow($tempcreditrent, $multiplier);
                        $changelog .= 'double-' . pow($tempcreditrent, $multiplier) . ';';
                    }
                }
            }

            if ($timeDiff > $this->watchesConfig['longrental'] * 3600) {
                $creditchange += $this->creditSystem->getLongRentalFee();
                $changelog .= 'longrent-' . $this->creditSystem->getLongRentalFee() . ';';
            }
            $userCredit -= $creditchange;
            if ($creditchange > 0) {
                $this->creditSystem->useCredit($userid, $creditchange);
            }

            $this->db->query(
                "INSERT INTO history SET userId = :userId, bikeNum = :bikeNum, action = :action, parameter = :creditChange, time = :time",
                [
                    'userId' => $userid,
                    'bikeNum' => $bike,
                    'action' => Action::CREDIT_CHANGE->value,
                    'creditChange' => $creditchange . '|' . $changelog,
                    'time' => $this->clock->now()->format('Y-m-d H:i:s'),
                ]
            );
            $this->db->query(
                "INSERT INTO history SET userId = :userId, bikeNum = :bikeNum, action = :action, parameter = :userCredit, time = :time",
                [
                    'userId' => $userid,
                    'bikeNum' => $bike,
                    'action' => Action::CREDIT->value,
                    'userCredit' => $userCredit,
                    'time' => $this->clock->now()->format('Y-m-d H:i:s'),
                ]
            );

            return $creditchange;
        }

        return null;
    }
}
