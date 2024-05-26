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
        global $db, $creditSystem, $user, $auth, $logger, $configuration, $smsSender;

        switch ($type) {
            case 'web':
                return new RentSystemWeb(
                    $db,
                    $creditSystem,
                    $user,
                    $auth,
                    $logger,
                    $configuration->get('watches'),
                    $configuration->get('connectors'),
                    (bool)$configuration->get('forcestack')
                );
            case 'sms':
                return new RentSystemSms(
                    $smsSender,
                    $db,
                    $creditSystem,
                    $user,
                    $auth,
                    $logger,
                    $configuration->get('watches'),
                    $configuration->get('connectors'),
                    (bool)$configuration->get('forcestack')
                );
            case 'qr':
                return new RentSystemQR(
                    $db,
                    $creditSystem,
                    $user,
                    $auth,
                    $logger,
                    $configuration->get('watches'),
                    $configuration->get('connectors'),
                    (bool)$configuration->get('forcestack')
                );
            default:
                throw new \InvalidArgumentException('Invalid rent system type');
        }
    }
}
