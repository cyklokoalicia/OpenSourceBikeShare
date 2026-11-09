<?php

declare(strict_types=1);

namespace BikeShare\Test\Application\Flow;

use BikeShare\Mail\MailSenderInterface;
use BikeShare\Repository\RegistrationRepository;
use BikeShare\Repository\UserRepository;
use BikeShare\Repository\UserSettingsRepository;
use BikeShare\Test\Application\BikeSharingWebTestCase;
use Monolog\Logger;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Translation\TranslatableMessage;

class RegistrationMailTest extends BikeSharingWebTestCase
{
    protected function setUp(): void
    {
        $this->setEnvVar('MESSENGER_CHATS_JSON', '[]');
        parent::setUp();
    }

    private function register(string $email): void
    {
        $this->client->request(Request::METHOD_GET, '/register');
        $this->assertResponseIsSuccessful();
        $this->client->submitForm('register', [
            'registration_form[fullname]' => 'John Doe',
            'registration_form[city]' => 'Default City',
            'registration_form[useremail]' => $email,
            'registration_form[password]' => 'password',
            'registration_form[password2]' => 'password',
            'registration_form[number]' => '+421901' . random_int(100000, 999999),
            'registration_form[agree]' => '1',
        ]);
    }

    private function uniqueEmail(): string
    {
        return 'regmail_' . bin2hex(random_bytes(4)) . '@example.com';
    }

    public function testRegistrationMailCarriesTheStoredKey(): void
    {
        $email = $this->uniqueEmail();
        $this->register($email);
        $this->assertResponseRedirects('/');

        $container = static::getContainer();
        $mails = $container->get(MailSenderInterface::class)->getSentMessages();
        $this->assertCount(1, $mails);
        $this->assertSame($email, $mails[0]['recipient']);

        $userId = $container->get(UserRepository::class)->findItemByEmail($email)['userId'];
        $key = $container->get(RegistrationRepository::class)->findItemByUserId($userId)['userKey'];
        $this->assertMatchesRegularExpression('/^[0-9a-f]{64}$/', $key);
        $this->assertStringEndsWith(
            '/user/confirm/email/' . $key,
            $this->confirmationLinkFrom($mails[0], 'success.registration.mail')
        );
    }

    public function testRegistrationMailUsesTheRegistrationLocale(): void
    {
        $email = $this->uniqueEmail();
        $this->client->request(Request::METHOD_GET, '/switchLanguage/sk');
        $this->register($email);
        $this->assertResponseRedirects('/');

        $container = static::getContainer();
        $mails = $container->get(MailSenderInterface::class)->getSentMessages();
        $this->assertCount(1, $mails);
        $this->assertSame('sk', $mails[0]['locale']);
        $this->assertEquals(new TranslatableMessage('Registration'), $mails[0]['subject']);

        $userId = $container->get(UserRepository::class)->findItemByEmail($email)['userId'];
        $this->assertSame('sk', $container->get(UserSettingsRepository::class)->findByUserId($userId)['locale']);
    }

    public function testRegistrationSucceedsWhenTheMailFails(): void
    {
        $this->expectLog(Logger::ERROR, '/Notification delivery failed/');
        $email = $this->uniqueEmail();
        $this->client->getContainer()->get(MailSenderInterface::class)->failFor($email);

        // One request only: a second one would reboot the kernel and drop failFor().
        $this->client->request(Request::METHOD_POST, '/register', [
            'registration_form' => [
                'fullname' => 'John Doe',
                'city' => 'Default City',
                'useremail' => $email,
                'password' => 'password',
                'password2' => 'password',
                'number' => '+421901' . random_int(100000, 999999),
                'agree' => '1',
            ],
        ]);
        $this->assertResponseRedirects('/');

        $container = static::getContainer();
        $user = $container->get(UserRepository::class)->findItemByEmail($email);
        $this->assertNotNull($user);
        $this->assertNotNull($container->get(RegistrationRepository::class)->findItemByUserId($user['userId']));
    }
}
