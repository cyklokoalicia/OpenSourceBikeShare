<?php

namespace BikeShare\Rent;

class RentSystemQR extends AbstractRentSystem implements RentSystemInterface
{
    public function rentBike($userId, $bikeId, $force = false)
    {
        $force = false; #rent by qr code can not be forced

        return parent::rentBike($userId, $bikeId, $force);
    }

    public function returnBike($userId, $bikeId, $standName, $note = '', $force = false)
    {
        $force = false; #return by qr code can not be forced
        $note = ''; #note can not be provided via qr code

        if ($bikeId !== 0) {
            $this->logger->error("Bike number could not be provided via QR code", ["userId" => $userId]);
            return $this->response(_('Invalid bike number'), ERROR);
        }

        $result = $this->db->query("SELECT bikeNum FROM bikes WHERE currentUser=$userId ORDER BY bikeNum");
        $bikeNumber = $result->rowCount();

        if ($bikeNumber > 1) {
            $message = _('You have') . ' ' . $bikeNumber . ' ' . _('rented bikes currently. QR code return can be used only when 1 bike is rented. Please, use web');
            if ($this->connectorsConfig["sms"]) {
                $message .= _(' or SMS');
            }
            $message .= _(' to return the bikes.');

            return $this->response($message, ERROR);
        }

        return parent::returnBike($userId, $bikeId, $standName, $note, $force);
    }

    protected function getRentSystemType() {
        return 'qr';
    }

    protected function response($message, $error = 0, $additional = '', $log = 1)
    {
        global $systemname, $systemURL;

        if ($log == 1 and $message) {
            $userid = $this->auth->getUserId();
            $number = $this->user->findPhoneNumber($userid);
            logresult($number, $message);
        }
        $this->db->commit();
        echo '<!DOCTYPE html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>',$systemname,'</title>';
        echo '<base href="',$systemURL,'" />';
        echo '<link rel="stylesheet" type="text/css" href="css/bootstrap.min.css" />';
        echo '<link rel="stylesheet" type="text/css" href="css/bootstrap-theme.min.css" />';
        echo '<link rel="apple-touch-icon" sizes="180x180" href="/apple-touch-icon.png">';
        echo '<link rel="icon" type="image/png" sizes="32x32" href="/favicon-32x32.png">';
        echo '<link rel="icon" type="image/png" sizes="16x16" href="/favicon-16x16.png">';
        echo '<link rel="manifest" href="/site.webmanifest">';
        echo '<link rel="mask-icon" href="/safari-pinned-tab.svg" color="#5bbad5">';
        echo '<meta name="msapplication-TileColor" content="#da532c">';
        echo '<meta name="theme-color" content="#ffffff">';
        if (file_exists("analytics.php")) require("analytics.php");
        echo '</head><body><div class="container">';
        if ($error)
        {
            echo '<div class="alert alert-danger" role="alert">',$message,'</div>';
        }
        else
        {
            echo '<div class="alert alert-success" role="alert">',$message,'</div>';
        }
        echo '</div></body></html>';
        exit;
    }
}