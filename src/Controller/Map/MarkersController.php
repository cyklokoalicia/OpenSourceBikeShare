<?php

namespace BikeShare\Controller\Map;

use BikeShare\Controller\AbstractController;

class MarkersController extends AbstractController
{
    /**
     * @phpcs:disable PSR12.Properties.ConstantVisibility
     */
    const NAME = 'map:markers';

    public static function getName()
    {
        return self::NAME;
    }

    #[Override]
    public function checkAccess()
    {
        return true;
    }

    public function run()
    {
        global $cities; #bad practice, configuration should be accessible via dependency

        $userId = $this->auth->getUserId();
        $userCity = '';
        if ($cities && !empty($userId)) {
            $userCity = ' AND city = "' . $this->user->findCity($userId) . '" ';
        }
        $markers = [];
        $result = $this->db->query(
            'SELECT 
                        standId,
                        count(bikeNum) AS bikecount,
                        standDescription,
                        standName,
                        standPhoto,
                        longitude AS lon,
                        latitude AS lat 
                   FROM stands 
                   LEFT JOIN bikes ON bikes.currentStand=stands.standId 
                   WHERE stands.serviceTag=0 ' . $userCity . '
                   GROUP BY standName ORDER BY standName'
        );
        while ($row = $result->fetch_assoc()) {
            $markers[] = $row;
        }

        return $markers;
    }
}
