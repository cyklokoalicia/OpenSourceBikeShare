<?php

declare(strict_types=1);

namespace BikeShare\Test\Application\Controller;

use BikeShare\App\Security\UserProvider;
use BikeShare\Repository\UserSettingsRepository;
use BikeShare\Test\Application\BikeSharingWebTestCase;
use Symfony\Component\HttpFoundation\Request;

class LanguageControllerTest extends BikeSharingWebTestCase
{
    private const USER_PHONE_NUMBER = '421951111111';

    public function setup(): void
    {
        parent::setUp();
        $user = $this->client->getContainer()->get(UserProvider::class)->loadUserByIdentifier(self::USER_PHONE_NUMBER);
        $this->client->getContainer()->get(UserSettingsRepository::class)->saveLocale($user->getUserId(), 'en');
    }

    public function tearDown(): void
    {
        $user = $this->client->getContainer()->get(UserProvider::class)->loadUserByIdentifier(self::USER_PHONE_NUMBER);
        $this->client->getContainer()->get(UserSettingsRepository::class)->saveLocale($user->getUserId(), 'en');
        parent::tearDown();
    }

    public function testLanguageChange(): void
    {
        $user = $this->client->getContainer()->get(UserProvider::class)->loadUserByIdentifier(self::USER_PHONE_NUMBER);
        $this->client->loginUser($user);
        $userSettingsRepository = $this->client->getContainer()->get(UserSettingsRepository::class);
        $userSettings = $userSettingsRepository->findByUserId($user->getUserId());
        $this->assertSame('en', $userSettings['locale']);

        $this->client->request(Request::METHOD_GET, '/switchLanguage/de');
        $this->assertResponseRedirects('/');

        $userSettings = $userSettingsRepository->findByUserId($user->getUserId());
        $this->assertSame('de', $userSettings['locale']);
    }
}
