<?php

declare(strict_types=1);

namespace BikeShare\Test\Application\Controller;

use BikeShare\App\Security\UserProvider;
use BikeShare\Repository\UserSettingsRepository;
use BikeShare\Test\Application\BikeSharingWebTestCase;

class UserSettingsControllerTest extends BikeSharingWebTestCase
{
    private const USER_PHONE_NUMBER = '421951111111';

    public function testChangeSettings(): void
    {
        $user = $this->client->getContainer()->get(UserProvider::class)->loadUserByIdentifier(self::USER_PHONE_NUMBER);
        $this->client->loginUser($user);
        $this->client->request('PUT', '/user/settings/geolocation', ['allowGeoDetection' => 'true']);

        $userSettingsRepository = $this->client->getContainer()->get(UserSettingsRepository::class);
        $userSettings = $userSettingsRepository->findByUserId($user->getUserId());
        $this->assertTrue($userSettings['allowGeoDetection']);

        $this->client->request('PUT', '/user/settings/geolocation', ['allowGeoDetection' => '0']);

        $userSettings = $userSettingsRepository->findByUserId($user->getUserId());
        $this->assertFalse($userSettings['allowGeoDetection']);
    }
}
