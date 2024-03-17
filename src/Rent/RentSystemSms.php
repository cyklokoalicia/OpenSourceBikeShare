<?php

namespace BikeShare\Rent;

class RentSystemSms implements RentSystemInterface
{
    public function rentBike($number, $bike, $force = false)
    {
        global $db, $forcestack, $watches, $smsSender, $user, $creditSystem;

        $stacktopbike = false;
        $userId = $user->findUserIdByNumber($number);

        $bikeNum = intval($bike);

        $result = $db->query("SELECT bikeNum FROM bikes WHERE bikeNum=$bikeNum");
        if ($result->num_rows != 1) {
            $smsSender->send($number, "Bike $bikeNum does not exist.");
            return;
        }

        if ($force == false) {
            if (!$creditSystem->isEnoughCreditForRent($userId)) {
                $minRequiredCredit = $creditSystem->getMinRequiredCredit();
                $userRemainingCredit = $creditSystem->getUserCredit($userId);
                $smsSender->send(
                    $number,
                    _('Please, recharge your credit:') . " " . $userRemainingCredit . $creditSystem->getCreditCurrency() . ". " . _('Credit required:') . " " . $minRequiredCredit . $creditSystem->getCreditCurrency() . "."
                );
                return;
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
                    $smsSender->send($number, _('You can not rent any bikes. Contact the admins to lift the ban.'));
                } elseif ($limit == 1) {
                    $smsSender->send($number, _('You can only rent') . ' ' . sprintf(ngettext('%d bike', '%d bikes', $limit), $limit) . ' ' . _('at once') . '.');
                } else {
                    $smsSender->send($number, _('You can only rent') . ' ' . sprintf(ngettext('%d bike', '%d bikes', $limit), $limit) . ' ' . _('at once') . ' ' . _('and you have already rented') . ' ' . $limit . '.');
                }
                return;
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
                    $smsSender->send($number, "Renting from service stands is not allowed: The bike probably waits for a repair.");
                    return;
                }

                if ($watches['stack'] and $stacktopbike != $bike) {
                    $result = $db->query("SELECT standName FROM stands WHERE standId='$standid'");
                    $row = $result->fetch_assoc();
                    $stand = $row['standName'];
                    $userName = $user->findUserName($userId);
                    notifyAdmins(_('Bike') . ' ' . $bike . ' ' . _('rented out of stack by') . ' ' . $userName . '. ' . $stacktopbike . ' ' . _('was on the top of the stack at') . ' ' . $stand . '.', ERROR);
                }
                if ($forcestack and $stacktopbike != $bike) {
                    $smsSender->send($number, _('Bike') . ' ' . $bike . ' ' . _('is not rentable now, you have to rent bike') . ' ' . $stacktopbike . ' ' . _('from this stand') . '.');
                    return;
                }
            }
        }

        $result = $db->query("SELECT currentUser,currentCode FROM bikes WHERE bikeNum=$bikeNum");
        $row = $result->fetch_assoc();
        $currentCode = sprintf('%04d', $row['currentCode']);
        $currentUser = $row['currentUser'];
        $result = $db->query("SELECT note FROM notes WHERE bikeNum='$bikeNum' AND deleted IS NULL ORDER BY time DESC LIMIT 1");
        $note = '';
        while ($row = $result->fetch_assoc()) {
            $note .= $row['note'] . '; ';
        }
        $note = substr($note, 0, strlen($note) - 2); // remove last two chars - comma and space

        $newCode = sprintf('%04d', rand(100, 9900)); //do not create a code with more than one leading zero or more than two leading 9s (kind of unusual/unsafe).

        if ($force == false) {
            if ($currentUser == $userId) {
                $smsSender->send($number, _('You have already rented the bike') . ' ' . $bikeNum . '. ' . _('Code is') . ' ' . $currentCode . '. ' . _('Return bike with command:') . ' RETURN ' . _('bikenumber') . ' ' . _('standname') . '.');
                return;
            }
            if ($currentUser != 0) {
                $smsSender->send($number, _('Bike') . ' ' . $bikeNum . ' ' . _('is already rented') . '.');
                return;
            }
        }

        $message = _('Bike') . ' ' . $bikeNum . ': ' . _('Open with code') . ' ' . $currentCode . '. ' . _('Change code immediately to') . ' ' . $newCode . ' ' . _('(open,rotate metal part,set new code,rotate metal part back)') . '.';
        if ($note) {
            $message .= '(' . _('bike note') . ':' . $note . ')';
        }

        $result = $db->query("UPDATE bikes SET currentUser=$userId,currentCode=$newCode,currentStand=NULL WHERE bikeNum=$bikeNum");
        if ($force == false) {
            $result = $db->query("INSERT INTO history SET userId=$userId,bikeNum=$bikeNum,action='RENT',parameter=$newCode");
        } else {
            $result = $db->query("INSERT INTO history SET userId=$userId,bikeNum=$bikeNum,action='FORCERENT',parameter=$newCode");
            $smsSender->send($number, _('System override') . ": " . _('Your rented bike') . " " . $bikeNum . " " . _('has been rented by admin') . ".");
        }
        $smsSender->send($number, $message);
    }

    public function returnBike($number, $bike, $stand, $message = '', $force = false)
    {
        global $db, $smsSender, $user, $creditSystem;
        $userId = $user->findUserIdByNumber($number);
        $bikeNum = intval($bike);
        $stand = strtoupper($stand);

        $result = $db->query("SELECT standId FROM stands WHERE standName='$stand'");
        if (!$result->num_rows) {
            $smsSender->send($number, _('Stand name') . " '" . $stand . "' " . _('does not exist. Stands are marked by CAPITALLETTERS.'));
            return;
        }
        $row = $result->fetch_assoc();
        $standId = $row["standId"];

        if ($force == false) {
            $result = $db->query("SELECT bikeNum FROM bikes WHERE currentUser=$userId ORDER BY bikeNum");
            $bikenumber = $result->num_rows;

            if ($bikenumber == 0) {
                $smsSender->send($number, _('You currently have no rented bikes.'));
                return;
            }

            $listBikes = [];
            while ($row = $result->fetch_assoc()) {
                $listBikes[] = $row["bikeNum"];
            }
            if ($bikenumber > 1)  {
                $listBikes = implode(',', $listBikes);
            }
        }

        if ($force == false) {
            $result = $db->query("SELECT currentCode FROM bikes WHERE currentUser=$userId AND bikeNum=$bikeNum");
            if ($result->num_rows != 1) {
                $smsSender->send($number, _('You does not have bike') . " " . $bikeNum . " rented. " . _('You have rented the following') . " " . sprintf(ngettext('%d bike', '%d bikes', $bikenumber), $bikenumber) . ": $listBikes");
                return;
            }
        } else {
            $result = $db->query("SELECT currentCode FROM bikes WHERE bikeNum=$bikeNum");
            if ($result->num_rows != 1) {
                $smsSender->send($number, _('Bike') . " " . $bikeNum . " " . _('is not rented. Saint Thomas, the patronus of all unrented bikes, prohibited returning unrented bikes.'));
                return;
            }
        }
        $row = $result->fetch_assoc();
        $currentCode = sprintf('%04d', $row['currentCode']);

        $result = $db->query("UPDATE bikes SET currentUser=NULL,currentStand=$standId WHERE bikeNum=$bikeNum");

        if ($message) {
            if (preg_match("/return[\s,\.]+[0-9]+[\s,\.]+[a-zA-Z0-9]+[\s,\.]+(.*)/i", $message, $matches)) {
                $note = $db->escape(trim($matches[1]));
            }

            addnote($userId, $bikeNum, $note);
        } else {
            $result = $db->query("SELECT note FROM notes WHERE bikeNum=$bikeNum AND deleted IS NULL ORDER BY time DESC LIMIT 1");
            $row = $result->fetch_assoc();
            $note = $row["note"];
        }

        $message = _('Bike') . ' ' . $bikeNum . ' ' . _('returned to stand') . ' ' . $stand . '. ' . _('Make sure you set code to') . ' ' . $currentCode . '.';
        if ($note) {
            $message .= "(note:" . $note . ")";
        }
        $message .= " " . _('Rotate lockpad to 0000.');

        if ($force == false) {
            $creditchange = changecreditendrental($bikeNum, $userId);
            if ($creditSystem->isEnabled() && $creditchange) {
                $userRemainingCredit = $creditSystem->getUserCredit($userId) . $creditSystem->getCreditCurrency();
                $message .= _('Credit') . ": " . $userRemainingCredit;
                $message .= " (-" . $creditchange . ")" . ".";
            }

            $result = $db->query("INSERT INTO history SET userId=$userId,bikeNum=$bikeNum,action='RETURN',parameter=$standId");
        } else {
            $result = $db->query("INSERT INTO history SET userId=$userId,bikeNum=$bikeNum,action='FORCERETURN',parameter=$standId");
        }

        $smsSender->send($number, $message);
    }
}