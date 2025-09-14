<?php

declare(strict_types=1);

namespace BikeShare\Test\Application\Controller;

use BikeShare\App\Security\UserProvider;
use BikeShare\Repository\UserSettingsRepository;
use BikeShare\Test\Application\BikeSharingWebTestCase;
use Symfony\Component\HttpFoundation\Request;

class LoginControllerTest extends BikeSharingWebTestCase
{
    private const USER_PHONE_NUMBER = '421951555555';
    private const USER_PHONE_PASSWORD = 'password';

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

    public function testLocaleSettingsOnLogin(): void
    {
        $user = $this->client->getContainer()->get(UserProvider::class)->loadUserByIdentifier(self::USER_PHONE_NUMBER);
        $this->client->request(
            Request::METHOD_POST,
            '/login',
            ['number' => self::USER_PHONE_NUMBER, 'password' => self::USER_PHONE_PASSWORD]
        );
        $this->assertSame('en', $this->client->getRequest()->getSession()->get('_locale'));

        $userSettingsRepository = $this->client->getContainer()->get(UserSettingsRepository::class);
        $userSettings = $userSettingsRepository->findByUserId($user->getUserId());
        $this->assertSame('en', $userSettings['locale']);

        $this->client->request(Request::METHOD_GET, '/switchLanguage/de');
        $this->assertSame('de', $this->client->getRequest()->getSession()->get('_locale'));

        $userSettings = $userSettingsRepository->findByUserId($user->getUserId());
        $this->assertSame('de', $userSettings['locale']);

        $this->client->request(Request::METHOD_GET, '/logout');

        $this->client->request(
            Request::METHOD_POST,
            '/login',
            ['number' => self::USER_PHONE_NUMBER, 'password' => self::USER_PHONE_PASSWORD]
        );
        $this->assertSame('de', $this->client->getRequest()->getSession()->get('_locale'));
    }
}
