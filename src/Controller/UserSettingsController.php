<?php

declare(strict_types=1);

namespace BikeShare\Controller;

use BikeShare\App\Entity\User;
use BikeShare\Repository\UserSettingsRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class UserSettingsController extends AbstractController
{
    public function __construct(private readonly UserSettingsRepository $userSettingsRepository)
    {
    }

    #[Route('/user/settings/geolocation', name: 'user_settings_geolocation', methods: ['PUT'])]
    public function saveGeolocation(): Response
    {
        /** @var User $user */
        $user = $this->getUser();
        if (!$user) {
            return new Response('User not authenticated', Response::HTTP_UNAUTHORIZED);
        }

        $userSettings = $this->userSettingsRepository->findByUserId($user->getUserId());

        if ($userSettings) {
            $settings = $userSettings['settings'];
            $settings['allowGeoDetection'] = true;
            $this->userSettingsRepository->update($userSettings['id'], $settings);
        } else {
            $this->userSettingsRepository->create($user->getUserId(), [
                'locale' => 'en', // default locale
                'allowGeoDetection' => true,
            ]);
        }

        return new Response('Geolocation preference saved.');
    }
}
