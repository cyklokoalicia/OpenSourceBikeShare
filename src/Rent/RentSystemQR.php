<?php

namespace BikeShare\Rent;

class RentSystemQR extends AbstractRentSystem implements RentSystemInterface
{
    public function rentBike($userId, $bikeId, $force = false)
    {
        $force = false; #rent by qr code cannot be forced

        return parent::rentBike($userId, $bikeId, $force);
    }

    public function returnBike($userId, $bikeId, $standName, $note = '', $force = false)
    {
        $force = false; #return by qr code cannot be forced
        $note = ''; #note cannot be provided via qr code

        if ($bikeId !== 0) {
            $this->logger->error("Bike number could not be provided via QR code", ["userId" => $userId]);
            return $this->response(_('Invalid bike number'), self::ERROR);
        }

        $result = $this->db->query("SELECT bikeNum FROM bikes WHERE currentUser=$userId ORDER BY bikeNum");
        $bikeNumber = $result->rowCount();

        if ($bikeNumber === 0) {
            return $this->response(_('You currently have no rented bikes.'), self::ERROR);
        } elseif ($bikeNumber > 1) {
            $message = _('You have') . ' ' . $bikeNumber . ' '
                . _('rented bikes currently. QR code return can be used only when 1 bike is rented. Please, use web');
            if ($this->isSmsSystemEnabled) {
                $message .= _(' or SMS');
            }
            $message .= _(' to return the bikes.');

            return $this->response($message, self::ERROR);
        }
        $bikeId = $result->fetchAssoc()['bikeNum'];

        return parent::returnBike($userId, $bikeId, $standName, $note, $force);
    }

    public function revertBike($userId, $bikeId)
    {
        return $this->response(_('Revert is not supported for QR code'), self::ERROR);
    }

    public static function getType(): string
    {
        return 'qr';
    }
}
