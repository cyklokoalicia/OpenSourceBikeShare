<?php

namespace BikeShare\Rent;

/**
 * @phpcs:disable Generic.Files.LineLength
 */
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

            return $this->response($this->translator->trans('Invalid bike number'), self::ERROR, 'Invalid bike number');
        }

        $result = $this->db->query("SELECT bikeNum FROM bikes WHERE currentUser=$userId ORDER BY bikeNum");
        $bikeNumber = $result->rowCount();

        if ($bikeNumber === 0) {
            return $this->response(
                $this->translator->trans('You currently have no rented bikes.'),
                self::ERROR,
                'You currently have no rented bikes.'
            );
        } elseif ($bikeNumber > 1) {
            $message = $this->translator->trans(
                'You have {bikeNumber} rented bikes currently. QR code return can be used only when 1 bike is rented. Please, use web or SMS to return the bikes.',
                ['bikeNumber' => $bikeNumber]
            );
            if (!$this->isSmsSystemEnabled) {
                $message = $this->translator->trans(
                    'You have {bikeNumber} rented bikes currently. QR code return can be used only when 1 bike is rented. Please, use web to return the bikes.',
                    ['bikeNumber' => $bikeNumber]
                );
            }

            return $this->response($message, self::ERROR);
        }

        $bikeId = $result->fetchAssoc()['bikeNum'];

        return parent::returnBike($userId, $bikeId, $standName, $note, $force);
    }

    public function revertBike($userId, $bikeId)
    {
        return $this->response(
            $this->translator->trans('Revert is not supported for QR code'),
            self::ERROR,
            'Revert is not supported for QR code'
        );
    }

    public static function getType(): string
    {
        return 'qr';
    }
}
