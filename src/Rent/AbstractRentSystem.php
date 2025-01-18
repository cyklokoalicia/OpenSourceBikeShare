<?php

namespace BikeShare\Rent;

use BikeShare\Authentication\Auth;
use BikeShare\Credit\CreditSystemInterface;
use BikeShare\Db\DbInterface;
use BikeShare\Event\BikeRentEvent;
use BikeShare\Event\BikeReturnEvent;
use BikeShare\Event\BikeRevertEvent;
use BikeShare\Notifier\AdminNotifier;
use BikeShare\User\User;
use Psr\Log\LoggerInterface;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * @phpcs:disable PSR12.Classes.PropertyDeclaration
 * @phpcs:disable Generic.Files.LineLength
 */
abstract class AbstractRentSystem implements RentSystemInterface
{
    protected const ERROR = 1;
    /**
     * @var DbInterface
     */
    protected $db;
    /**
     * @var CreditSystemInterface
     */
    protected $creditSystem;
    /**
     * @var User
     */
    protected $user;
    /**
     * @var Auth
     */
    protected $auth;
    /**
     * @var EventDispatcherInterface
     */
    protected $eventDispatcher;
    /**
     * @var AdminNotifier
     */
    protected $adminNotifier;
    /**
     * @var LoggerInterface
     */
    protected $logger;
    /**
     * @var array
     */
    protected $watchesConfig;
    protected bool $isSmsSystemEnabled;
    /**
     * @var false
     */
    protected $forceStack;

    public function __construct(
        DbInterface $db,
        CreditSystemInterface $creditSystem,
        User $user,
        Auth $auth,
        EventDispatcherInterface $eventDispatcher,
        AdminNotifier $adminNotifier,
        LoggerInterface $logger,
        array $watchesConfig,
        bool $isSmsSystemEnabled,
        $forceStack = false
    ) {
        $this->db = $db;
        $this->creditSystem = $creditSystem;
        $this->user = $user;
        $this->auth = $auth;
        $this->eventDispatcher = $eventDispatcher;
        $this->adminNotifier = $adminNotifier;
        $this->logger = $logger;
        $this->watchesConfig = $watchesConfig;
        $this->isSmsSystemEnabled = $isSmsSystemEnabled;
        $this->forceStack = $forceStack;
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

            if ($this->forceStack or $this->watchesConfig['stack']) {
                $result = $this->db->query("SELECT currentStand FROM bikes WHERE bikeNum='$bikeId'");
                $row = $result->fetchAssoc();
                $standid = $row['currentStand'];
                $stacktopbike = $this->checktopofstack($standid);

                $result = $this->db->query("SELECT serviceTag FROM stands WHERE standId='$standid'");
                $row = $result->fetchAssoc();
                $serviceTag = $row['serviceTag'];

                if ($serviceTag != 0) {
                    return $this->response(_('Renting from service stands is not allowed: The bike probably waits for a repair.'), self::ERROR);
                }

                if ($this->watchesConfig['stack'] and $stacktopbike != $bikeId) {
                    $result = $this->db->query("SELECT standName FROM stands WHERE standId='$standid'");
                    $row = $result->fetchAssoc();
                    $stand = $row['standName'];
                    $userName = $this->user->findUserName($userId);
                    $this->notifyAdmins(_('Bike') . ' ' . $bikeId . ' ' . _('rented out of stack by') . ' ' . $userName . '. ' . $stacktopbike . ' ' . _('was on the top of the stack at') . ' ' . $stand . '.', false);
                }
                if ($this->forceStack and $stacktopbike != $bikeId) {
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
        $note = substr($note, 0, strlen($note) - 2); // remove last two chars - comma and space

        $newCode = sprintf('%04d', rand(100, 9900)); //do not create a code with more than one leading zero or more than two leading 9s (kind of unusual/unsafe).

        if ($force == false) {
            if ($currentUser == $userId) {
                return $this->response(_('You have already rented the bike') . ' ' . $bikeNum . '. ' . _('Code is') . ' ' . $currentCode . '.', self::ERROR);
            }
            if ($currentUser != 0) {
                return $this->response(_('Bike') . ' ' . $bikeNum . ' ' . _('is already rented') . '.', self::ERROR);
            }
        }

        $message = '<h3>' . _('Bike') . ' ' . $bikeNum . ': <span class="label label-primary">' . _('Open with code') . ' ' . $currentCode . '.</span></h3><h3>' . _('Change code immediately to') . ' <span class="label label-default" style="font-size: 16px;">' . $newCode . '</span></h3>' . _('(open, rotate metal part, set new code, rotate metal part back)') . '.';
        if ($note) {
            $message .= '<br />' . _('Reported issue') . ': <em>' . $note . '</em>';
        }

        $result = $this->db->query("UPDATE bikes SET currentUser=$userId,currentCode=$newCode,currentStand=NULL WHERE bikeNum=$bikeNum");
        if ($force == false) {
            $result = $this->db->query("INSERT INTO history SET userId=$userId,bikeNum=$bikeNum,action='RENT',parameter=$newCode");
        } else {
            $result = $this->db->query("INSERT INTO history SET userId=$userId,bikeNum=$bikeNum,action='FORCERENT',parameter=$newCode");
            //$this->response(_('System override') . ": " . _('Your rented bike') . " " . $bikeNum . " " . _('has been rented by admin') . ".");
        }

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
            $result = $this->db->query("SELECT bikeNum FROM bikes WHERE currentUser=$userId ORDER BY bikeNum");
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

        $message = '<h3>' . _('Bike') . ' ' . $bikeNum . ' ' . _('returned to stand') . ' ' . $stand . ' : <span class="label label-primary">' . _('Lock with code') . ' ' . $currentCode . '.</span></h3>';
        $message .= '<br />' . _('Please') . ', <strong>' . _('rotate the lockpad to') . ' <span class="label label-default">0000</span></strong> ' . _('when leaving') . '.' . _('Wipe the bike clean if it is dirty, please') . '.';
        if ($note) {
            $message .= '<br />' . _('You have also reported this problem:') . ' ' . $note . '.';
        }

        if ($force == false) {
            $creditchange = $this->changecreditendrental($bikeNum, $userId);
            if ($this->creditSystem->isEnabled() && $creditchange) {
                $message .= '<br />' . _('Credit change') . ': -' . $creditchange . $this->creditSystem->getCreditCurrency() . '.';
            }

            $result = $this->db->query("INSERT INTO history SET userId=$userId,bikeNum=$bikeNum,action='RETURN',parameter=$standId");
        } else {
            $result = $this->db->query("INSERT INTO history SET userId=$userId,bikeNum=$bikeNum,action='FORCERETURN',parameter=$standId");
        }

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
            return $this->response(_('Bicycle') . ' ' . $bikeId . ' ' . _('is not rented right now. Revert not successful!'), ERROR);
        } else {
            $row = $result->fetchAssoc();
            $previousOwnerId = $row['currentUser'];
        }
        $result = $this->db->query(
            "SELECT parameter,standName 
                   FROM stands
                   LEFT JOIN history ON stands.standId=parameter
                   WHERE bikeNum=$bikeId 
                     AND action IN ('RETURN','FORCERETURN') 
                   ORDER BY time DESC
                   LIMIT 1"
        );
        if ($result->rowCount() === 1) {
            $row = $result->fetchAssoc();
            $standId = $row['parameter'];
            $stand = $row['standName'];
        }
        $result = $this->db->query(
            "SELECT parameter 
                   FROM history 
                   WHERE bikeNum=$bikeId 
                     AND action IN ('RENT','FORCERENT') 
                   ORDER BY time DESC
                   LIMIT 1,1"
        );
        if ($result->rowCount() == 1) {
            $row = $result->fetchAssoc();
            $code = str_pad($row['parameter'], 4, '0', STR_PAD_LEFT);
        }
        if ($standId && $code) {
            $this->db->query("UPDATE bikes SET currentUser=NULL,currentStand=$standId,currentCode=$code WHERE bikeNum=$bikeId");
            $this->db->query("INSERT INTO history SET userId=$userId,bikeNum=$bikeId,action='REVERT',parameter='$standId|$code'");
            $this->db->query("INSERT INTO history SET userId=0,bikeNum=$bikeId,action='RENT',parameter=$code");
            $this->db->query("INSERT INTO history SET userId=0,bikeNum=$bikeId,action='RETURN',parameter=$standId");

            $this->eventDispatcher->dispatch(
                new BikeRevertEvent($bikeId, $userId, $previousOwnerId)
            );

            return $this->response('<h3>' . _('Bicycle') . ' ' . $bikeId . ' ' . _('reverted to') . ' <span class="label label-primary">' . $stand . '</span> ' . _('with code') . ' <span class="label label-primary">' . $code . '</span>.</h3>');
        } else {
            return $this->response(_('No last stand or code for bicycle') . ' ' . $bikeId . ' ' . _('found. Revert not successful!'), ERROR);
        }
    }

    abstract public static function getType(): string;

    protected function response($message, $error = 0)
    {
        $userid = $this->auth->getUserId();
        $number = $this->user->findPhoneNumber($userid);
        $this->logResult($number, $message);

        return [
            'error' => $error,
            'content' => $message,
        ];
    }

    private function checktopofstack($standid)
    {
        return checktopofstack($standid);
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
    private function changecreditendrental($bike, $userid)
    {
        if ($this->creditSystem->isEnabled() == false) {
            return;
        }
        // if credit system disabled, exit

        $userCredit = $this->creditSystem->getUserCredit($userid);

        $result = $this->db->query("SELECT time FROM history WHERE bikeNum=$bike AND userId=$userid AND (action='RENT' OR action='FORCERENT') ORDER BY time DESC LIMIT 1");
        if ($result->rowCount() == 1) {
            $row = $result->fetchAssoc();
            $starttime = strtotime($row['time']);
            $endtime = time();
            $timediff = $endtime - $starttime;
            $creditchange = 0;
            $changelog = '';

            //ak vrati a znova pozica bike do 10 min tak free time nebude mať.
            $oldRetrun = $this->db->query("SELECT time FROM history WHERE bikeNum=$bike AND userId=$userid AND (action='RETURN' OR action='FORCERETURN') ORDER BY time DESC LIMIT 1");
            if ($oldRetrun->rowCount() == 1) {
                $oldRow = $oldRetrun->fetchAssoc();
                $returntime = strtotime($oldRow["time"]);
                if (($starttime - $returntime) < 10 * 60 && $timediff > 5 * 60) {
                    $creditchange = $creditchange + $this->creditSystem->getRentalFee();
                    $changelog .= 'rerent-' . $this->creditSystem->getRentalFee() . ';';
                }
            }
            //end

            if ($timediff > $this->watchesConfig['freetime'] * 60) {
                $creditchange = $creditchange + $this->creditSystem->getRentalFee();
                $changelog .= 'overfree-' . $this->creditSystem->getRentalFee() . ';';
            }
            if ($this->watchesConfig['freetime'] == 0) {
                $this->watchesConfig['freetime'] = 1;
            }
            // for further calculations
            if ($this->creditSystem->getPriceCycle() && $timediff > $this->watchesConfig['freetime'] * 60 * 2) {
                // after first paid period, i.e. freetime*2; if pricecycle enabled
                $temptimediff = $timediff - ($this->watchesConfig['freetime'] * 60 * 2);
                if ($this->creditSystem->getPriceCycle() == 1) { // flat price per cycle
                    $cycles = ceil($temptimediff / ($this->watchesConfig['flatpricecycle'] * 60));
                    $creditchange = $creditchange + ($this->creditSystem->getRentalFee() * $cycles);
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

                        $creditchange = $creditchange + pow($tempcreditrent, $multiplier);
                        $changelog .= 'double-' . pow($tempcreditrent, $multiplier) . ';';
                    }
                }
            }
            if ($timediff > $this->watchesConfig['longrental'] * 3600) {
                $creditchange = $creditchange + $this->creditSystem->getLongRentalFee();
                $changelog .= 'longrent-' . $this->creditSystem->getLongRentalFee() . ';';
            }
            $userCredit = $userCredit - $creditchange;
            $this->db->query("UPDATE credit SET credit=$userCredit WHERE userId=$userid");
            $this->db->query("INSERT INTO history SET userId=$userid,bikeNum=$bike,action='CREDITCHANGE',parameter='" . $creditchange . '|' . $changelog . "'");
            $this->db->query("INSERT INTO history SET userId=$userid,bikeNum=$bike,action='CREDIT',parameter=$userCredit");

            return $creditchange;
        }
    }

    private function logResult($number, $message)
    {
        logresult($number, $message);
    }
}
