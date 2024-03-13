<?php

namespace BikeShare\Controller\Map;

use BikeShare\Controller\AbstractController;

class GeoLocationController extends AbstractController
{
    /**
     * @phpcs:disable PSR12.Properties.ConstantVisibility
     */
    const NAME = 'map:geolocation';

    public static function getName()
    {
        return self::NAME;
    }

    #[Override]
    public function checkAccess()
    {
        return !empty($this->userId);
    }

    public function run()
    {
        if (!$this->request->query->has('latitude') || !$this->request->query->has('longitude')) {
            throw new \InvalidArgumentException('Latitude and longitude are required');
        }
        $userId = $this->auth->getUserId();
        $latitude = floatval($this->request->query->get('latitude'));
        $longitude = floatval($this->request->query->get('longitude'));
        $this->db->query("INSERT INTO geolocation SET userId='$userId',latitude='$latitude',longitude='$longitude'");

        return null;
    }
}
