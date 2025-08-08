<?php

namespace BikeShare\Rent;

use BikeShare\Credit\CreditSystemInterface;
use BikeShare\Db\DbInterface;
use BikeShare\Event\BikeRentEvent;
use BikeShare\Event\BikeReturnEvent;
use BikeShare\Event\BikeRevertEvent;
use BikeShare\Notifier\AdminNotifier;
use BikeShare\Repository\StandRepository;
use BikeShare\User\User;
use BikeShare\History\HistoryAction;
use Psr\Log\LoggerInterface;
use Symfony\Component\Clock\ClockInterface;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * @phpcs:disable PSR12.Classes.PropertyDeclaration
 * @phpcs:disable Generic.Files.LineLength
 */
abstract class AbstractRentSystem implements RentSystemInterface
{
    protected const ERROR = 1;

    public function __construct(
        protected DbInterface $db,
        protected CreditSystemInterface $creditSystem,
        protected User $user,
        protected EventDispatcherInterface $eventDispatcher,
        protected AdminNotifier $adminNotifier,
        protected LoggerInterface $logger,
        protected StandRepository $standRepository,
        protected ClockInterface $clock,
        protected array $watchesConfig,
        protected bool $isSmsSystemEnabled,
        protected bool $forceStack,
    ) {
    }

    public function rentBike($userId, $bikeId, $force = false)
    {
        $stacktopbike = false;
        $userId = intval($userId);
        $bikeNum = intval($bikeId);

        $result = $this->db->query("SELECT bikeNum FROM bikes WHERE bikeNum=$bikeNum");
        if ($result->rowCount() != 1) {
            return $this->response(_('Bike') . ' ' . $bikeNum . ' ' . _('does not exist.'), self::ERROR);
        }

        if ($force == false) {
            if (!$this->creditSystem->isEnoughCreditForRent($userId)) {
                $minRequiredCredit = $this->creditSystem->getMinRequiredCredit();
                return $this->response(_('You are below required credit') . ' ' . $minRequiredCredit . $this->creditSystem->getCreditCurrency() . '. ' . _('Please, recharge your credit.'), self::ERROR);
            }

            $result = $this->db->query("SELECT count(*) as countRented FROM bikes where currentUser=$userId");
            $row = $result->fetchAssoc();
            $countRented = $row['countRented'];

            $result = $this->db->query("SELECT userLimit FROM limits where userId=$userId");
            $row = $result->fetchAssoc();
            $limit = $row['userLimit'];

            if ($countRented >= $limit) {
                if ($limit == 0) {
                    return $this->response(_('You can not rent any bikes. Contact the admins to lift the ban.'), self::ERROR);
                } elseif ($limit == 1) {
                    return $this->response(_('You can only rent') . ' ' . sprintf(ngettext('%d bike', '%d bikes', $limit), $limit) . ' ' . _('at once') . '.', self::ERROR);
                } else {
                    return $this->response(_('You can only rent') . ' ' . sprintf(ngettext('%d bike', '%d bikes', $limit), $limit) . ' ' . _('at once') . ' ' . _('and you have already rented') . ' ' . $limit . '.', self::ERROR);
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
                    return $this->response(_('Renting from service stands is not allowed: The bike probably waits for a repair.'), self::ERROR);
                }

                if ($this->watchesConfig['stack'] && $stacktopbike != $bikeId) {
                    $result = $this->db->query("SELECT standName FROM stands WHERE standId='$standid'");
                    $row = $result->fetchAssoc();
                    $stand = $row['standName'];
                    $userName = $this->user->findUserName($userId);
                    $this->notifyAdmins(_('Bike') . ' ' . $bikeId . ' ' . _('rented out of stack by') . ' ' . $userName . '. ' . $stacktopbike . ' ' . _('was on the top of the stack at') . ' ' . $stand . '.', false);
                }

                if ($this->forceStack && $stacktopbike != $bikeId) {
                    return $this->response(_('Bike') . ' ' . $bikeId . ' ' . _('is not rentable now, you have to rent bike') . ' ' . $stacktopbike . ' ' . _('from this stand') . '.', self::ERROR);
                }
            }
        }

        $result = $this->db->query("SELECT currentUser,currentCode FROM bikes WHERE bikeNum=$bikeNum");
        $row = $result->fetchAssoc();
        $currentCode = sprintf('%04d', $row['currentCode']);
        $currentUser = $row['currentUser'];
        $result = $this->db->query("SELECT note FROM notes WHERE bikeNum='$bikeNum' AND deleted IS NULL ORDER BY time DESC");
        $note = '';
        while ($row = $result->fetchAssoc()) {
            $note .= $row['note'] . '; ';
        }

        $note = substr($note, 0, strlen($note) - 2); // remove the last two chars - comma and space

        $newCode = sprintf('%04d', rand(100, 9900)); //do not create a code with more than one leading zero or more than two leading 9s (kind of unusual/unsafe).

        if ($force == false) {
            if ($currentUser == $userId) {
                return $this->response(_('You have already rented the bike') . ' ' . $bikeNum . '. ' . _('Code is') . ' ' . $currentCode . '.', self::ERROR);
            }

            if ($currentUser != 0) {
                return $this->response(_('Bike') . ' ' . $bikeNum . ' ' . _('is already rented') . '.', self::ERROR);
            }
        }

        $message = '<h3>' . _('Bike') . ' ' . $bikeNum . ': <span class="badge badge-primary">' . _('Open with code') . ' ' . $currentCode . '.</span></h3>'
            . '<h3>' . _('Change code immediately to') . ' <span class="badge badge-primary">' . $newCode . '</span></h3>'
            . _('(open, rotate metal part, set new code, rotate metal part back)') . '.';
        if ($note) {
            $message .= '<br />' . _('Reported issue') . ': <em>' . $note . '</em>';
        }

        $result = $this->db->query("UPDATE bikes SET currentUser=$userId,currentCode=$newCode,currentStand=NULL WHERE bikeNum=$bikeNum");
        if ($force) {
            //$this->response(_('System override') . ": " . _('Your rented bike') . " " . $bikeNum . " " . _('has been rented by admin') . ".");
        }
        $result = $this->db->query(
            "INSERT INTO history SET userId = :userId, bikeNum = :bikeNum, action = :action, parameter = :newCode, time = :time",
            [
                'userId' => $userId,
                'bikeNum' => $bikeNum,
                'action' => $force ? HistoryAction::FORCERENT->value : HistoryAction::RENT->value,
                'newCode' => $newCode,
                'time' => $this->clock->now()->format('Y-m-d H:i:s'),
            ]
        );

        $this->eventDispatcher->dispatch(
            new BikeRentEvent($bikeNum, $userId, $force)
        );

        return $this->response($message);
    }

    public function returnBike($userId, $bikeId, $standName, $note = '', $force = false)
    {
        $userId = intval($userId);
        $bikeNum = intval($bikeId);
        $stand = strtoupper($standName);

        $result = $this->db->query("SELECT standId FROM stands WHERE standName='$stand'");
        if (!$result->rowCount()) {
            return $this->response(_('Stand name') . " '" . $stand . "' " . _('does not exist. Stands are marked by CAPITALLETTERS.'), self::ERROR);
        }

        $row = $result->fetchAssoc();
        $standId = $row["standId"];

        if ($force == false) {
            $result = $this->db->query("SELECT bikeNum FROM bikes WHERE currentUser=$userId AND bikeNum=$bikeId ORDER BY bikeNum");
            $bikenumber = $result->rowCount();

            if ($bikenumber == 0) {
                return $this->response(_('You currently have no rented bikes.'), self::ERROR);
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

        $message = '<h3>' . _('Bike') . ' ' . $bikeNum . ' ' . _('returned to stand') . ' ' . $stand
            . ' : <span class="badge badge-primary">' . _('Lock with code') . ' ' . $currentCode . '.</span></h3>'
            . '<br />' . _('Please') . ', <strong>' . _('rotate the lockpad to')
            . ' <span class="badge badge-primary">0000</span></strong> ' . _('when leaving') . '.' . _('Wipe the bike clean if it is dirty, please') . '.';
        if ($note) {
            $message .= '<br />' . _('You have also reported this problem:') . ' ' . $note . '.';
        }

        if ($force == false) {
            $creditchange = $this->changecreditendrental($bikeNum, $userId);
            if ($this->creditSystem->isEnabled() && $creditchange) {
                $message .= '<br />' . _('Credit change') . ': -' . $creditchange . $this->creditSystem->getCreditCurrency() . '.';
            }
        }
        $result = $this->db->query(
            "INSERT INTO history SET userId = :userId, bikeNum = :bikeNum, action = :action, parameter = :standId, time = :time",
            [
                'userId' => $userId,
                'bikeNum' => $bikeNum,
                'action' => $force ? HistoryAction::FORCERETURN->value : HistoryAction::RETURN->value,
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
            return $this->response(_('Bicycle') . ' ' . $bikeId . ' ' . _('is not rented right now. Revert not successful!'), self::ERROR);
        } else {
            $row = $result->fetchAssoc();
            $previousOwnerId = $row['currentUser'];
        }

        $result = $this->db->query(
            sprintf(
                "SELECT parameter,standName
                   FROM stands
                   LEFT JOIN history ON stands.standId=parameter
                   WHERE bikeNum=%d
                     AND action IN ('%s','%s')
                   ORDER BY time DESC
                   LIMIT 1",
                $bikeId,
                HistoryAction::RETURN->value,
                HistoryAction::FORCERETURN->value
            )
        );
        if ($result->rowCount() === 1) {
            $row = $result->fetchAssoc();
            $standId = $row['parameter'];
            $stand = $row['standName'];
        }

        $result = $this->db->query(
            sprintf(
                "SELECT parameter
                   FROM history
                   WHERE bikeNum=%d
                     AND action IN ('%s','%s')
                   ORDER BY time DESC
                   LIMIT 1",
                $bikeId,
                HistoryAction::RENT->value,
                HistoryAction::FORCERENT->value
            )
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
                    'action' => HistoryAction::REVERT->value,
                    'parameter' => "$standId|$code",
                    'time' => $this->clock->now()->format('Y-m-d H:i:s'),
                ]
            );
            $this->db->query(
                "INSERT INTO history SET userId = :userId, bikeNum = :bikeNum, action = :action, parameter = :code, time = :time",
                [
                    'userId' => $userId,
                    'bikeNum' => $bikeId,
                    'action' => HistoryAction::RENT->value,
                    'code' => $code,
                    'time' => $this->clock->now()->format('Y-m-d H:i:s'),
                ]
            );
            $this->db->query(
                "INSERT INTO history SET userId = :userId, bikeNum = :bikeNum, action = :action, parameter = :standId, time = :time",
                [
                    'userId' => $userId,
                    'bikeNum' => $bikeId,
                    'action' => HistoryAction::RETURN->value,
                    'standId' => $standId,
                    'time' => $this->clock->now()->format('Y-m-d H:i:s'),
                ]
            );

            $this->eventDispatcher->dispatch(
                new BikeRevertEvent($bikeId, $userId, $previousOwnerId)
            );

            return $this->response('<h3>' . _('Bike') . ' ' . $bikeId . ' ' . _('reverted to') . ' <span class="badge badge-primary">' . $stand . '</span> ' . _('with code') . ' <span class="badge badge-primary">' . $code . '</span>.</h3>');
        } else {
            return $this->response(_('No last stand or code for bicycle') . ' ' . $bikeId . ' ' . _('found. Revert not successful!'), self::ERROR);
        }
    }

    abstract public static function getType(): string;

    protected function response($message, $error = 0)
    {
        return [
            'error' => $error,
            'message' => $message,
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
            $bikeStatus = _('at') . ' ' . $standName;
        } else {
            $bikeStatus = _('used by') . ' ' . $userName . ' +' . $phone;
        }
        $this->db->query("INSERT INTO notes SET bikeNum='$bikeNum',userId='$userId',note='$userNote'");
        $noteid = $this->db->getLastInsertId();
        $this->notifyAdmins(_('Note #') . $noteid . ': b.' . $bikeNum . ' (' . $bikeStatus . ') ' . _('by') . ' ' . $userName . '/' . $phone . ':' . $userNote);
    }

    // subtract credit for rental
    private function changecreditendrental($bike, $userid): ?float
    {
        if ($this->creditSystem->isEnabled() === false) {
            return null;
        }

        $userCredit = $this->creditSystem->getUserCredit($userid);

        $result = $this->db->query(
            sprintf(
                "SELECT time FROM history WHERE bikeNum=%d AND userId=%d AND (action='%s' OR action='%s') ORDER BY time DESC LIMIT 1",
                $bike,
                $userid,
                HistoryAction::RENT->value,
                HistoryAction::FORCERENT->value
            )
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
                sprintf(
                    "SELECT time FROM history WHERE bikeNum=%d AND userId=%d AND (action='%s' OR action='%s') ORDER BY time DESC LIMIT 1",
                    $bike,
                    $userid,
                    HistoryAction::RETURN->value,
                    HistoryAction::FORCERETURN->value
                )
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
                    'action' => HistoryAction::CREDITCHANGE->value,
                    'creditChange' => $creditchange . '|' . $changelog,
                    'time' => $this->clock->now()->format('Y-m-d H:i:s'),
                ]
            );
            $this->db->query(
                "INSERT INTO history SET userId = :userId, bikeNum = :bikeNum, action = :action, parameter = :userCredit, time = :time",
                [
                    'userId' => $userid,
                    'bikeNum' => $bike,
                    'action' => HistoryAction::CREDIT->value,
                    'userCredit' => $userCredit,
                    'time' => $this->clock->now()->format('Y-m-d H:i:s'),
                ]
            );

            return $creditchange;
        }

        return null;
    }
}
