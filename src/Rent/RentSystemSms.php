<?php

namespace BikeShare\Rent;

class RentSystemSms extends AbstractRentSystem implements RentSystemInterface
{
    private $number;

    public function rentBike($number, $bike, $force = false)
    {
        global $db, $forcestack, $watches, $user, $creditSystem;

        $this->number = $number;
        $userId = $user->findUserIdByNumber($number);

        $stacktopbike = false;
        $bikeNum = intval($bike);

        $result = $db->query("SELECT bikeNum FROM bikes WHERE bikeNum=$bikeNum");
        if ($result->num_rows != 1) {
            return $this->response(_('Bike') . ' ' . $bikeNum . ' ' . _('does not exist.'), ERROR);
        }

        if ($force == false) {
            if (!$creditSystem->isEnoughCreditForRent($userId)) {
                $minRequiredCredit = $creditSystem->getMinRequiredCredit();
                return $this->response(_('You are below required credit') . ' ' . $minRequiredCredit . $creditSystem->getCreditCurrency() . '. ' . _('Please, recharge your credit.'), ERROR);
            }

            checktoomany(0, $userId);

            $result = $db->query("SELECT count(*) as countRented FROM bikes where currentUser=$userId");
            $row = $result->fetch_assoc();
            $countRented = $row['countRented'];

            $result = $db->query("SELECT userLimit FROM limits where userId=$userId");
            $row = $result->fetch_assoc();
            $limit = $row['userLimit'];

            if ($countRented >= $limit) {
                if ($limit == 0) {
                    return $this->response(_('You can not rent any bikes. Contact the admins to lift the ban.'), ERROR);
                } elseif ($limit == 1) {
                    return $this->response(_('You can only rent') . ' ' . sprintf(ngettext('%d bike', '%d bikes', $limit), $limit) . ' ' . _('at once') . '.', ERROR);
                } else {
                    return $this->response(_('You can only rent') . ' ' . sprintf(ngettext('%d bike', '%d bikes', $limit), $limit) . ' ' . _('at once') . ' ' . _('and you have already rented') . ' ' . $limit . '.', ERROR);
                }
            }

            if ($forcestack or $watches['stack']) {
                $result = $db->query("SELECT currentStand FROM bikes WHERE bikeNum='$bike'");
                $row = $result->fetch_assoc();
                $standid = $row['currentStand'];
                $stacktopbike = checktopofstack($standid);

                $result = $db->query("SELECT serviceTag FROM stands WHERE standId='$standid'");
                $row = $result->fetch_assoc();
                $serviceTag = $row['serviceTag'];

                if ($serviceTag != 0) {
                    return $this->response(_('Renting from service stands is not allowed: The bike probably waits for a repair.'), ERROR);
                }

                if ($watches['stack'] and $stacktopbike != $bike) {
                    $result = $db->query("SELECT standName FROM stands WHERE standId='$standid'");
                    $row = $result->fetch_assoc();
                    $stand = $row['standName'];
                    $userName = $user->findUserName($userId);
                    notifyAdmins(_('Bike') . ' ' . $bike . ' ' . _('rented out of stack by') . ' ' . $userName . '. ' . $stacktopbike . ' ' . _('was on the top of the stack at') . ' ' . $stand . '.', ERROR);
                }
                if ($forcestack and $stacktopbike != $bike) {
                    return $this->response(_('Bike') . ' ' . $bike . ' ' . _('is not rentable now, you have to rent bike') . ' ' . $stacktopbike . ' ' . _('from this stand') . '.', ERROR);
                }
            }
        }

        $result = $db->query("SELECT currentUser,currentCode FROM bikes WHERE bikeNum=$bikeNum");
        $row = $result->fetch_assoc();
        $currentCode = sprintf('%04d', $row['currentCode']);
        $currentUser = $row['currentUser'];
        $result = $db->query("SELECT note FROM notes WHERE bikeNum='$bikeNum' AND deleted IS NULL ORDER BY time DESC");
        $note = '';
        while ($row = $result->fetch_assoc()) {
            $note .= $row['note'] . '; ';
        }
        $note = substr($note, 0, strlen($note) - 2); // remove last two chars - comma and space

        $newCode = sprintf('%04d', rand(100, 9900)); //do not create a code with more than one leading zero or more than two leading 9s (kind of unusual/unsafe).

        if ($force == false) {
            if ($currentUser == $userId) {
                return $this->response(_('You have already rented the bike') . ' ' . $bikeNum . '. ' . _('Code is') . ' ' . $currentCode . '.', ERROR);
            }
            if ($currentUser != 0) {
                return $this->response(_('Bike') . ' ' . $bikeNum . ' ' . _('is already rented') . '.', ERROR);
            }
        }

        $message = _('Bike') . ' ' . $bikeNum . ': ' . _('Open with code') . ' ' . $currentCode . '. ' . _('Change code immediately to') . ' ' . $newCode . ' ' . _('(open, rotate metal part, set new code, rotate metal part back)') . '.';
        if ($note) {
            $message .= '(' . _('Reported issue') . ':' . $note . ')';
        }

        $result = $db->query("UPDATE bikes SET currentUser=$userId,currentCode=$newCode,currentStand=NULL WHERE bikeNum=$bikeNum");
        if ($force == false) {
            $result = $db->query("INSERT INTO history SET userId=$userId,bikeNum=$bikeNum,action='RENT',parameter=$newCode");
        } else {
            $result = $db->query("INSERT INTO history SET userId=$userId,bikeNum=$bikeNum,action='FORCERENT',parameter=$newCode");
            //$this->response(_('System override') . ": " . _('Your rented bike') . " " . $bikeNum . " " . _('has been rented by admin') . ".");
        }
        return $this->response($message);
    }

    public function returnBike($number, $bike, $stand, $note = '', $force = false)
    {
        global $db, $connectors, $user, $creditSystem;

        $this->number = $number;
        $userId = $user->findUserIdByNumber($number);

        $bikeNum = intval($bike);
        $stand = strtoupper($stand);

        $result = $db->query("SELECT standId FROM stands WHERE standName='$stand'");
        if (!$result->num_rows) {
            return $this->response(_('Stand name') . " '" . $stand . "' " . _('does not exist. Stands are marked by CAPITALLETTERS.'), ERROR);
        }
        $row = $result->fetch_assoc();
        $standId = $row["standId"];

        if ($force == false) {
            $result = $db->query("SELECT bikeNum FROM bikes WHERE currentUser=$userId ORDER BY bikeNum");
            $bikenumber = $result->num_rows;

            if ($bikenumber == 0) {
                return $this->response(_('You currently have no rented bikes.'), ERROR);
            } elseif ($this->getRentSystemType() === 'qr' && $bikenumber > 1) {
                $message = _('You have') . ' ' . $bikenumber . ' ' . _('rented bikes currently. QR code return can be used only when 1 bike is rented. Please, use web');
                if ($connectors["sms"]) {
                    $message .= _(' or SMS');
                }
                $message .= _(' to return the bikes.');
                return $this->response($message, ERROR);
            }
        }

        if ($force == false) {
            $result = $db->query("SELECT currentCode FROM bikes WHERE currentUser=$userId AND bikeNum=$bikeNum");
        } else {
            $result = $db->query("SELECT currentCode FROM bikes WHERE bikeNum=$bikeNum");
        }
        $row = $result->fetch_assoc();
        $currentCode = sprintf('%04d', $row['currentCode']);

        $result = $db->query("UPDATE bikes SET currentUser=NULL,currentStand=$standId WHERE bikeNum=$bikeNum and currentUser=$userId");
        if ($note) {
            if (preg_match("/return[\s,\.]+[0-9]+[\s,\.]+[a-zA-Z0-9]+[\s,\.]+(.*)/i", $note, $matches)) {
                //this should be checked before calling return bike
                $note = $db->escape(trim($matches[1]));
            }

            addnote($userId, $bikeNum, $note);
        } else {
            $result = $db->query("SELECT note FROM notes WHERE bikeNum=$bikeNum AND deleted IS NULL ORDER BY time DESC LIMIT 1");
            $row = $result->fetch_assoc();
            $note = $row["note"];
        }

        $message = _('Bike') . ' ' . $bikeNum . ' ' . _('returned to stand') . ' ' . $stand . ' ' . _('Lock with code') . ' ' . $currentCode . '.';
        $message .= _('Please') . ', ' . _('rotate the lockpad to') . ' 0000 ' . _('when leaving') . '.' . _('Wipe the bike clean if it is dirty, please') . '.';
        if ($note) {
            $message .= _('You have also reported this problem:'). ' ' . $note . '.';
        }

        if ($force == false) {
            $creditchange = changecreditendrental($bikeNum, $userId);
            if ($creditSystem->isEnabled() && $creditchange) {
                $message .= '<br />' . _('Credit change') . ': -' . $creditchange . $creditSystem->getCreditCurrency() . '.';
            }

            $result = $db->query("INSERT INTO history SET userId=$userId,bikeNum=$bikeNum,action='RETURN',parameter=$standId");
        } else {
            $result = $db->query("INSERT INTO history SET userId=$userId,bikeNum=$bikeNum,action='FORCERETURN',parameter=$standId");
        }

        return $this->response($message);
    }

    protected function getRentSystemType() {
        return 'sms';
    }

    protected function response($message, $error = 0, $additional = '', $log = 1)
    {
        global $smsSender;
        
        $smsSender->send($this->number, $message);
    }
}