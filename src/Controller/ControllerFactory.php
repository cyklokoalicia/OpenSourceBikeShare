<?php

namespace BikeShare\Controller;

use BikeShare\Authentication\Auth;
use BikeShare\Controller\Map\MarkersController;
use BikeShare\Db\DbInterface;
use Bikeshare\Controller\Map\GeoLocationController;
use BikeShare\User\User;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Request;

class ControllerFactory
{
    private $controllerMap = [
        GeoLocationController::NAME => GeoLocationController::class, #map:geolocation
        MarkersController::NAME => MarkersController::class, #map:status
    ];

    /**
     * @var DbInterface
     */
    private $db;
    /**
     * @var Auth
     */
    private $auth;
    /**
     * @var User
     */
    private $user;
    /**
     * @var LoggerInterface
     */
    private $logger;

    public function __construct(
        Request $request,
        DbInterface $db,
        Auth $auth,
        User $user,
        LoggerInterface $logger
    ) {
        $this->request = $request;
        $this->db = $db;
        $this->auth = $auth;
        $this->user = $user;
        $this->logger = $logger;
    }

    /**
     * @param string $controllerName
     * @return null|AbstractController
     */
    public function getController($controllerName)
    {
        if (isset($this->controllerMap[$controllerName])) {
            return new $this->controllerMap[$controllerName](
                $this->request,
                $this->db,
                $this->auth,
                $this->user,
                $this->logger
            );
        }

        return null;
    }
}