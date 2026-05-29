<?php

declare(strict_types=1);

namespace BikeShare\Test\Application\Controller;

use BikeShare\App\Security\UserProvider;
use BikeShare\Repository\UserRepository;
use BikeShare\Repository\UserSettingsRepository;
use BikeShare\Test\Application\BikeSharingWebTestCase;
use Symfony\Component\HttpFoundation\Request;

class WelcomeControllerTest extends BikeSharingWebTestCase
{
    private const USER_PHONE_NUMBER = '421951555555';
    private const USER_PASSWORD = 'password';

    private const CHATS_JSON = '[{"name":"Test Telegram","url":"https://t.me/test"},'
        . '{"name":"Test Discord","url":"https://discord.gg/test"}]';

    private ?string $previousChatsEnv = null;

    protected function setUp(): void
    {
        $this->previousChatsEnv = $_SERVER['MESSENGER_CHATS_JSON'] ?? '';
        $_SERVER['MESSENGER_CHATS_JSON'] = self::CHATS_JSON;
        $_ENV['MESSENGER_CHATS_JSON'] = self::CHATS_JSON;

        parent::setUp();
        $this->resetWelcomeFlag();
    }

    protected function tearDown(): void
    {
        $this->resetWelcomeFlag();
        $_SERVER['MESSENGER_CHATS_JSON'] = $this->previousChatsEnv;
        $_ENV['MESSENGER_CHATS_JSON'] = $this->previousChatsEnv;
        parent::tearDown();
    }

    public function testLoginRedirectsToWelcomeWhenFlagIsSet(): void
    {
        $this->setShowWelcomePage(true);

        $this->client->request(
            Request::METHOD_POST,
            '/login',
            ['number' => self::USER_PHONE_NUMBER, 'password' => self::USER_PASSWORD]
        );

        $this->assertResponseRedirects('/welcome');
    }

    public function testLoginDoesNotRedirectToWelcomeWhenFlagIsAbsent(): void
    {
        $this->client->request(
            Request::METHOD_POST,
            '/login',
            ['number' => self::USER_PHONE_NUMBER, 'password' => self::USER_PASSWORD]
        );

        $this->assertResponseRedirects();
        $this->assertNotSame('/welcome', parse_url(
            (string)$this->client->getResponse()->headers->get('Location'),
            PHP_URL_PATH
        ));
    }

    public function testWelcomePageRendersChatsAndQrCodes(): void
    {
        $this->loginUser();
        $crawler = $this->client->request(Request::METHOD_GET, '/welcome');

        $this->assertResponseIsSuccessful();
        $this->assertStringContainsString('Test Telegram', $crawler->text());
        $this->assertStringContainsString('Test Discord', $crawler->text());
        $this->assertGreaterThanOrEqual(2, $crawler->filter('svg')->count());
    }

    public function testDismissClearsFlagAndRedirectsHome(): void
    {
        $this->setShowWelcomePage(true);
        $this->loginUser();

        $this->client->request(Request::METHOD_GET, '/welcome');
        $this->assertResponseIsSuccessful();

        $this->client->request(Request::METHOD_POST, '/welcome/dismiss');

        $this->assertResponseRedirects('/');

        $userSettings = $this->getSettingsRepository()->findByUserId($this->getUserId());
        $this->assertFalse($userSettings['showWelcomePage']);
    }

    public function testLoginAfterDismissNoLongerRedirects(): void
    {
        $this->setShowWelcomePage(false);

        $this->client->request(
            Request::METHOD_POST,
            '/login',
            ['number' => self::USER_PHONE_NUMBER, 'password' => self::USER_PASSWORD]
        );

        $this->assertResponseRedirects();
        $this->assertNotSame('/welcome', parse_url(
            (string)$this->client->getResponse()->headers->get('Location'),
            PHP_URL_PATH
        ));
    }

    public function testWelcomeRedirectsHomeWhenNoChatsConfigured(): void
    {
        $_SERVER['MESSENGER_CHATS_JSON'] = '[]';
        $_ENV['MESSENGER_CHATS_JSON'] = '[]';
        self::ensureKernelShutdown();
        $this->client = static::createClient();

        $this->setShowWelcomePage(true);
        $this->loginUser();
        $this->client->request(Request::METHOD_GET, '/welcome');

        $this->assertResponseRedirects('/');
    }

    public function testRegistrationSetsShowWelcomePageFlag(): void
    {
        $userEmail = 'welcome_' . time() . '@example.com';
        $userPhone = '+421902' . rand(100000, 999999);

        $this->client->request(Request::METHOD_GET, '/register');
        $this->assertResponseIsSuccessful();

        $this->client->submitForm(
            'register',
            [
                'registration_form[fullname]' => 'Welcome User',
                'registration_form[city]' => 'Default City',
                'registration_form[useremail]' => $userEmail,
                'registration_form[password]' => 'password',
                'registration_form[password2]' => 'password',
                'registration_form[number]' => $userPhone,
                'registration_form[agree]' => '1',
            ]
        );
        $this->assertResponseRedirects('/');

        $user = $this->client->getContainer()->get(UserRepository::class)
            ->findItemByEmail($userEmail);
        $this->assertNotNull($user, 'New user was not persisted');

        $settings = $this->getSettingsRepository()->findByUserId($user['userId']);
        $this->assertTrue(
            $settings['showWelcomePage'],
            'Expected showWelcomePage=true on a user registered while MESSENGER_CHATS_JSON is non-empty'
        );
    }

    private function loginUser(): void
    {
        $user = $this->client->getContainer()->get(UserProvider::class)
            ->loadUserByIdentifier(self::USER_PHONE_NUMBER);
        $this->client->loginUser($user);
    }

    private function setShowWelcomePage(bool $value): void
    {
        $this->getSettingsRepository()->saveShowWelcomePage($this->getUserId(), $value);
    }

    private function resetWelcomeFlag(): void
    {
        $this->getSettingsRepository()->saveShowWelcomePage($this->getUserId(), false);
    }

    private function getSettingsRepository(): UserSettingsRepository
    {
        return $this->client->getContainer()->get(UserSettingsRepository::class);
    }

    private function getUserId(): int
    {
        $user = $this->client->getContainer()->get(UserProvider::class)
            ->loadUserByIdentifier(self::USER_PHONE_NUMBER);

        return $user->getUserId();
    }
}
