<?php

declare(strict_types=1);

namespace BikeShare\Controller;

use BikeShare\App\Entity\User;
use BikeShare\Repository\UserSettingsRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class UserSettingsController extends AbstractController
{
    public function __construct(
        private readonly UserSettingsRepository $userSettingsRepository
    ) {
    }

    #[Route('/user/settings/geolocation', name: 'user_settings_geolocation', methods: ['PUT'])]
    public function saveGeolocation(
        Request $request,
    ): Response
    {
        $allowGeoDetection = $request->request->getBoolean('allowGeoDetection');
        /** @var User $user */
        $user = $this->getUser();

        $this->userSettingsRepository->saveAllowGeoLocation($user->getUserId(), $allowGeoDetection);

        return new Response('Geolocation preference saved.');
    }
}
