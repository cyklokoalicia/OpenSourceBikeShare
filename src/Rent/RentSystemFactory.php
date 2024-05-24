<?php

namespace BikeShare\Rent;

class RentSystemFactory
{
    /**
     * @param string $type
     * @return RentSystemInterface
     */
    public static function create($type)
    {
        global $db, $creditSystem, $user, $auth, $logger, $forcestack, $watches, $connectors, $smsSender;

        switch ($type) {
            case 'web':
                return new RentSystemWeb(
                    $db,
                    $creditSystem,
                    $user,
                    $auth,
                    $logger,
                    $watches,
                    $connectors,
                    (bool)$forcestack
                );
            case 'sms':
                return new RentSystemSms(
                    $smsSender,
                    $db,
                    $creditSystem,
                    $user,
                    $auth,
                    $logger,
                    $watches,
                    $connectors,
                    (bool)$forcestack
                );
            case 'qr':
                return new RentSystemQR(
                    $db,
                    $creditSystem,
                    $user,
                    $auth,
                    $logger,
                    $watches,
                    $connectors,
                    (bool)$forcestack
                );
            default:
                throw new \InvalidArgumentException('Invalid rent system type');
        }
    }
}
