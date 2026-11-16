<?php

declare(strict_types=1);

namespace BikeShare\Test\Application\Controller;

use BikeShare\App\Entity\User;
use BikeShare\App\Security\UserProvider;
use BikeShare\Db\DbInterface;
use BikeShare\Mail\MailSenderInterface;
use BikeShare\Repository\UserRepository;
use BikeShare\Repository\PasswordResetRepository;
use BikeShare\Test\Application\BikeSharingWebTestCase;
use BikeShare\Test\Assert\MailAssertions;
use Monolog\Logger;
use Symfony\Component\Clock\Test\ClockSensitiveTrait;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\Translation\TranslatableMessage;
use Symfony\Contracts\Translation\TranslatorInterface;

class ResetPasswordTest extends BikeSharingWebTestCase
{
    use ClockSensitiveTrait;

    private const USER_ID = 4;
    private const USER_PHONE_NUMBER = '421951111111';
    private const USER_PASSWORD = 'password';
    private const ADMIN_PHONE_NUMBER = '421951222222';
    private const UNKNOWN_NUMBER = '421999999999';
    private const CLIENT_IP = '127.0.0.1';
    private const IP_LIMIT = 10;
    private const NUMBER_LIMIT = 3;

    private string $originalPasswordHash;
    private string $originalMail;

    protected function setUp(): void
    {
        parent::setUp();
        // Fixtures give the user a random password; the API login and the profile form need a known one.
        $this->client->getContainer()->get(DbInterface::class)->query(
            'UPDATE users SET password = :password WHERE userId = :userId',
            [
                'password' => password_hash(self::USER_PASSWORD, PASSWORD_BCRYPT, ['cost' => 4]),
                'userId' => self::USER_ID,
            ]
        );
        $this->originalPasswordHash = $this->readPasswordHash();
        $userRepository = $this->client->getContainer()->get(UserRepository::class);
        $this->originalMail = $userRepository->findItem(self::USER_ID)['mail'];
        $this->resetLimiters();
    }

    protected function tearDown(): void
    {
        $db = $this->client->getContainer()->get(DbInterface::class);
        $db->query(
            'UPDATE users SET password = :password, number = :number, mail = :mail WHERE userId = :userId',
            [
                'password' => $this->originalPasswordHash,
                'number' => self::USER_PHONE_NUMBER,
                'mail' => $this->originalMail,
                'userId' => self::USER_ID,
            ]
        );
        $this->client->getContainer()->get(PasswordResetRepository::class)->deleteByUserId(self::USER_ID);

        parent::tearDown();
    }

    public function testRequestMailsALinkAndKeepsThePassword(): void
    {
        $this->requestReset(self::USER_PHONE_NUMBER);

        $this->assertResponseRedirects('/resetPassword');
        $this->assertFlash('success', 'password.reset.requested');
        $this->assertSame($this->originalPasswordHash, $this->readPasswordHash(), 'A request changes no credentials');

        $container = $this->client->getContainer();
        $userItem = $container->get(UserRepository::class)->findItem(self::USER_ID);
        $mails = $container->get(MailSenderInterface::class)->getSentMessages();
        $this->assertCount(1, $mails);
        $this->assertSame($userItem['mail'], $mails[0]['recipient']);
        $this->assertEquals(new TranslatableMessage('Password reset'), $mails[0]['subject']);
        $message = $mails[0]['message'];
        $this->assertInstanceOf(TranslatableMessage::class, $message);
        $this->assertSame('password.reset.link.mail', $message->getMessage());
        $parameters = $message->getParameters();
        $this->assertSame(['name', 'resetUrl', 'minutes'], array_keys($parameters), 'No password in the mail');
        $this->assertSame(preg_split('/[\s,]+/', $userItem['userName'])[0], $parameters['name']);
        $this->assertSame(60, $parameters['minutes']);
        $this->assertMatchesRegularExpression(
            '#^http://localhost/resetPassword/[0-9a-f]{64}$#',
            $parameters['resetUrl']
        );

        $rendered = $message->trans($container->get(TranslatorInterface::class), $mails[0]['locale']);
        $this->assertStringContainsString($parameters['resetUrl'], $rendered);
    }

    public function testLinkSetsTheNewPasswordOnce(): void
    {
        $newSecret = bin2hex(random_bytes(6));
        $this->requestReset(self::USER_PHONE_NUMBER);
        $resetPath = $this->resetPathFromMail();

        $this->setNewPasswordThroughTheLink($resetPath, $newSecret);

        $this->assertResponseRedirects('/login');
        $this->assertFlash('success', 'password.reset.done');
        $container = $this->client->getContainer();
        $user = $container->get(UserProvider::class)->loadUserByIdentifier(self::USER_PHONE_NUMBER);
        $this->assertTrue($container->get(UserPasswordHasherInterface::class)->isPasswordValid($user, $newSecret));

        $this->client->request(Request::METHOD_GET, $resetPath);
        $this->assertResponseRedirects('/resetPassword', null, 'A used link does not work again');
        $this->assertFlash('error', 'password.reset.invalid_link');
    }

    public function testResetEndsTheAppSessionsOfTheOldPassword(): void
    {
        $this->client->request(Request::METHOD_POST, '/api/v1/auth/token', [
            'number' => self::USER_PHONE_NUMBER,
            'password' => self::USER_PASSWORD,
        ]);
        $this->assertResponseIsSuccessful();
        $refreshToken = $this->decodeApiResponseData()['refreshToken'];

        $this->requestReset(self::USER_PHONE_NUMBER);
        $this->setNewPasswordThroughTheLink($this->resetPathFromMail());
        $this->assertResponseRedirects('/login');

        $this->client->request(Request::METHOD_POST, '/api/v1/auth/refresh', ['refreshToken' => $refreshToken]);
        $this->assertResponseStatusCodeSame(401);
    }

    public function testLinkWorksWhenTheStoredNumberIsNoLongerAValidPhoneNumber(): void
    {
        $this->requestReset(self::USER_PHONE_NUMBER);
        $resetPath = $this->resetPathFromMail();
        $this->client->getContainer()->get(DbInterface::class)->query(
            'UPDATE users SET number = :number WHERE userId = :userId',
            ['number' => '12', 'userId' => self::USER_ID]
        );

        $this->setNewPasswordThroughTheLink($resetPath);

        $this->assertResponseRedirects('/login');
        $this->assertNotSame($this->originalPasswordHash, $this->readPasswordHash());
    }

    public function testChangingThePasswordInTheProfileCancelsThePendingLink(): void
    {
        $this->requestReset(self::USER_PHONE_NUMBER);
        $resetPath = $this->resetPathFromMail();

        $this->client->loginUser($this->loadUser(self::USER_PHONE_NUMBER));
        $newSecret = bin2hex(random_bytes(6));
        $crawler = $this->client->request(Request::METHOD_GET, '/user/profile');
        $this->client->submit($crawler->selectButton('Change Password')->form([
            'change_password_form[currentPassword]' => self::USER_PASSWORD,
            'change_password_form[plainPassword][first]' => $newSecret,
            'change_password_form[plainPassword][second]' => $newSecret,
        ]));
        $this->assertResponseRedirects('/user/profile');

        $this->client->request(Request::METHOD_GET, $resetPath);
        $this->assertResponseRedirects('/resetPassword');
        $this->assertFlash('error', 'password.reset.invalid_link');
    }

    public function testAnAdminChangeOfTheMailCancelsThePendingLink(): void
    {
        $this->requestReset(self::USER_PHONE_NUMBER);
        $this->client->loginUser($this->loadUser(self::ADMIN_PHONE_NUMBER));

        $this->editUserAsAdmin($this->originalMail);
        $this->assertSame(1, $this->countResetTokens(), 'Other edits keep the link');

        $this->editUserAsAdmin('replaced_' . $this->originalMail);
        $this->assertSame(0, $this->countResetTokens());
    }

    public function testRequestForAnotherHostIsRejected(): void
    {
        $this->expectLog(Logger::ERROR, '/Untrusted Host "evil\\.example"/');

        $this->requestReset(self::USER_PHONE_NUMBER, ['HTTP_HOST' => 'evil.example']);

        $this->assertResponseStatusCodeSame(400);
        $this->assertSame([], $this->sentMails());
        $this->assertSame(0, $this->countResetTokens());
    }

    public function testMismatchedPasswordsChangeNothing(): void
    {
        $this->requestReset(self::USER_PHONE_NUMBER);

        $this->client->request(Request::METHOD_GET, $this->resetPathFromMail());
        $this->client->submitForm('reset-password', [
            'change_password_form[plainPassword][first]' => bin2hex(random_bytes(6)),
            'change_password_form[plainPassword][second]' => bin2hex(random_bytes(6)),
        ]);

        $this->assertResponseStatusCodeSame(422);
        $this->assertSame($this->originalPasswordHash, $this->readPasswordHash());
        $this->assertSame(1, $this->countResetTokens(), 'The link stays usable after a typo');
    }

    public function testExpiredLinkIsRejected(): void
    {
        $this->requestReset(self::USER_PHONE_NUMBER);
        $resetPath = $this->resetPathFromMail();
        static::mockTime('+61 minutes');

        $this->client->request(Request::METHOD_GET, $resetPath);

        $this->assertResponseRedirects('/resetPassword');
        $this->assertFlash('error', 'password.reset.invalid_link');
    }

    public function testUnknownLinkIsRejected(): void
    {
        $this->client->request(Request::METHOD_GET, '/resetPassword/' . str_repeat('a', 64));

        $this->assertResponseRedirects('/resetPassword');
        $this->assertFlash('error', 'password.reset.invalid_link');
    }

    public function testRequestWithoutCsrfTokenIsRejected(): void
    {
        $this->client->request(
            Request::METHOD_POST,
            '/resetPassword',
            ['reset_password_request_form' => ['number' => self::USER_PHONE_NUMBER]]
        );

        $this->assertResponseStatusCodeSame(422);
        $this->assertSame([], $this->sentMails());
        $this->assertSame(0, $this->countResetTokens());
    }

    public function testFailedMailKeepsTheOldPassword(): void
    {
        $this->expectLog(
            Logger::ERROR,
            '/Notification delivery failed/',
            ['type' => 'password-reset', 'channel' => 'email', 'userId' => self::USER_ID]
        );
        $container = $this->client->getContainer();
        $userMail = $container->get(UserRepository::class)->findItem(self::USER_ID)['mail'];
        $container->get(MailSenderInterface::class)->failFor($userMail);

        $this->requestReset(self::USER_PHONE_NUMBER);

        $this->assertResponseRedirects('/resetPassword');
        $this->assertFlash('success', 'password.reset.requested');
        $this->assertSame(
            $this->originalPasswordHash,
            $this->readPasswordHash(),
            'A lost mail cannot lock the user out'
        );
    }

    public function testUnknownNumberLooksTheSameAndSendsNothing(): void
    {
        $this->requestReset(self::UNKNOWN_NUMBER);

        $this->assertResponseRedirects('/resetPassword');
        $this->assertFlash('success', 'password.reset.requested');
        $this->assertSame([], $this->sentMails());
    }

    public function testOneNumberGetsALimitedNumberOfMails(): void
    {
        $mailsSent = 0;
        for ($i = 0; $i <= self::NUMBER_LIMIT; $i++) {
            $this->requestReset(self::USER_PHONE_NUMBER);
            $this->assertResponseRedirects('/resetPassword');
            $this->assertFlash('success', 'password.reset.requested');
            $mailsSent += count($this->sentMails());
        }

        $this->assertSame(self::NUMBER_LIMIT, $mailsSent);
    }

    public function testOneClientGetsALimitedNumberOfRequests(): void
    {
        foreach ($this->otherNumbers() as $number) {
            $this->requestReset($number);
            $this->assertFlash('success', 'password.reset.requested');
        }

        $successFlashes = count($this->flashes('success'));
        $this->requestReset(self::USER_PHONE_NUMBER);

        $this->assertResponseRedirects('/resetPassword');
        $this->assertFlash('error', 'password.reset.too_many');
        $this->assertCount($successFlashes, $this->flashes('success'), 'A limited request is not reported as sent');
        $this->assertSame([], $this->sentMails());
    }

    /**
     * @param array<string, string> $server
     */
    private function requestReset(string $number, array $server = []): void
    {
        $this->client->request(Request::METHOD_POST, '/resetPassword', [
            'reset_password_request_form' => [
                '_token' => $this->csrfToken('reset_password_request_form'),
                'number' => $number,
            ],
        ], server: $server);
    }

    private function editUserAsAdmin(string $mail): void
    {
        $userItem = $this->client->getContainer()->get(UserRepository::class)->findItem(self::USER_ID);
        $this->client->request(
            Request::METHOD_PATCH,
            '/api/v1/admin/users/' . self::USER_ID,
            server: ['CONTENT_TYPE' => 'application/json'],
            content: json_encode([
                'username' => $userItem['userName'],
                'email' => $mail,
                'number' => $userItem['number'],
                'privileges' => $userItem['privileges'],
                'userLimit' => $userItem['userLimit'],
            ], JSON_THROW_ON_ERROR)
        );
        $this->assertResponseIsSuccessful();
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function sentMails(): array
    {
        return $this->client->getContainer()->get(MailSenderInterface::class)->getSentMessages();
    }

    private function setNewPasswordThroughTheLink(string $resetPath, ?string $newSecret = null): void
    {
        $newSecret ??= bin2hex(random_bytes(6));
        $this->client->request(Request::METHOD_GET, $resetPath);
        $this->assertResponseIsSuccessful();
        $this->assertResponseHeaderSame('Referrer-Policy', 'no-referrer');
        $this->client->submitForm('reset-password', [
            'change_password_form[plainPassword][first]' => $newSecret,
            'change_password_form[plainPassword][second]' => $newSecret,
        ]);
    }

    private function loadUser(string $number): User
    {
        $user = $this->client->getContainer()->get(UserProvider::class)->loadUserByIdentifier($number);
        $this->assertInstanceOf(User::class, $user);

        return $user;
    }

    private function resetPathFromMail(): string
    {
        $mails = $this->sentMails();
        $this->assertCount(1, $mails);

        return MailAssertions::assertLink($mails[0], 'password.reset.link.mail', 'resetUrl');
    }

    private function assertFlash(string $type, string $key): void
    {
        $translator = $this->client->getContainer()->get(TranslatorInterface::class);
        // Flashes of earlier requests stay in the bag: the tests never follow the redirect that would show them.
        $this->assertContains($translator->trans($key), $this->flashes($type));
    }

    /**
     * @return list<string>
     */
    private function flashes(string $type): array
    {
        $session = $this->client->getRequest()->getSession();
        $this->assertInstanceOf(Session::class, $session);

        return array_values($session->getFlashBag()->peek($type));
    }

    /**
     * @return list<string>
     */
    private function otherNumbers(): array
    {
        return array_map(static fn (int $i): string => '4219000000' . $i, range(10, 9 + self::IP_LIMIT));
    }

    private function resetLimiters(): void
    {
        $container = $this->client->getContainer();
        $ipLimiter = $container->get('limiter.password_reset_ip');
        $numberLimiter = $container->get('limiter.password_reset_number');
        $this->assertInstanceOf(RateLimiterFactory::class, $ipLimiter);
        $this->assertInstanceOf(RateLimiterFactory::class, $numberLimiter);

        $ipLimiter->create(self::CLIENT_IP)->reset();
        foreach ([self::USER_PHONE_NUMBER, self::UNKNOWN_NUMBER, ...$this->otherNumbers()] as $number) {
            $numberLimiter->create($number)->reset();
        }
    }

    private function countResetTokens(): int
    {
        return count($this->client->getContainer()->get(DbInterface::class)
            ->query('SELECT 1 FROM passwordReset WHERE userId = :userId', ['userId' => self::USER_ID])
            ->fetchAllAssoc());
    }

    private function readPasswordHash(): string
    {
        return $this->client->getContainer()->get(DbInterface::class)->query(
            'SELECT password FROM users WHERE userId = :userId',
            ['userId' => self::USER_ID]
        )->fetchAssoc()['password'];
    }
}
