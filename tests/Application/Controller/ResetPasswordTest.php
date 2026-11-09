<?php

declare(strict_types=1);

namespace BikeShare\Test\Application\Controller;

use BikeShare\App\Security\UserProvider;
use BikeShare\Db\DbInterface;
use BikeShare\Mail\MailSenderInterface;
use BikeShare\Repository\UserRepository;
use BikeShare\Test\Application\BikeSharingWebTestCase;
use BikeShare\Translation\PlainText;
use Monolog\Logger;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

class ResetPasswordTest extends BikeSharingWebTestCase
{
    private const USER_ID = 4;
    private const USER_PHONE_NUMBER = '421951111111';

    private string $originalPasswordHash;

    protected function setUp(): void
    {
        parent::setUp();
        $this->originalPasswordHash = $this->readPasswordHash();
    }

    protected function tearDown(): void
    {
        $this->client->getContainer()->get(DbInterface::class)->query(
            'UPDATE users SET password = :password WHERE userId = :userId',
            ['password' => $this->originalPasswordHash, 'userId' => self::USER_ID]
        );

        parent::tearDown();
    }

    public function testResetMailsTheNewPassword(): void
    {
        $this->client->request(Request::METHOD_POST, '/resetPassword', ['number' => self::USER_PHONE_NUMBER]);

        $this->assertResponseRedirects('/resetPassword');

        $container = $this->client->getContainer();
        $userMail = $container->get(UserRepository::class)->findItem(self::USER_ID)['mail'];
        $mails = $container->get(MailSenderInterface::class)->getSentMessages();
        $this->assertCount(1, $mails);
        $mail = $mails[0];
        $this->assertSame($userMail, $mail['recipient']);
        $this->assertInstanceOf(PlainText::class, $mail['subject']);
        $this->assertInstanceOf(PlainText::class, $mail['message']);

        $translator = $container->get(TranslatorInterface::class);
        $this->assertSame('Password reset', $mail['subject']->trans($translator));
        $body = $mail['message']->trans($translator);
        $this->assertStringContainsString("Your new password is:\n", $body);
        $lines = explode("\n", $body);
        $newPassword = end($lines);
        $this->assertSame(8, strlen($newPassword));
        $this->assertFlashSuccess();

        $user = $container->get(UserProvider::class)->loadUserByIdentifier(self::USER_PHONE_NUMBER);
        $this->assertTrue($container->get(UserPasswordHasherInterface::class)->isPasswordValid($user, $newPassword));
    }

    public function testResetSucceedsWhenTheMailFails(): void
    {
        $this->expectLog(Logger::ERROR, '/Notification delivery failed/');

        $container = $this->client->getContainer();
        $userMail = $container->get(UserRepository::class)->findItem(self::USER_ID)['mail'];
        $container->get(MailSenderInterface::class)->failFor($userMail);

        $this->client->request(Request::METHOD_POST, '/resetPassword', ['number' => self::USER_PHONE_NUMBER]);

        $this->assertResponseRedirects('/resetPassword');
        $this->assertFlashSuccess();
    }

    public function testUnknownNumberSendsNothing(): void
    {
        $this->client->request(Request::METHOD_POST, '/resetPassword', ['number' => '421999999999']);

        $this->assertResponseRedirects('/resetPassword');
        $this->assertSame(
            [],
            $this->client->getContainer()->get(MailSenderInterface::class)->getSentMessages()
        );
        $this->assertFlashSuccess();
    }

    private function assertFlashSuccess(): void
    {
        $session = $this->client->getRequest()->getSession();
        $this->assertInstanceOf(Session::class, $session);
        $flashes = $session->getFlashBag()->peek('success');
        $this->assertCount(1, $flashes);
        $this->assertStringContainsString('Your password has been reset successfully.', $flashes[0]);
    }

    private function readPasswordHash(): string
    {
        return $this->client->getContainer()->get(DbInterface::class)->query(
            'SELECT password FROM users WHERE userId = :userId',
            ['userId' => self::USER_ID]
        )->fetchAssoc()['password'];
    }
}
