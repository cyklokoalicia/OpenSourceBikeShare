<?php

declare(strict_types=1);

namespace BikeShare\Test\Application\Flow;

use BikeShare\Mail\MailSenderInterface;
use BikeShare\Repository\RegistrationRepository;
use BikeShare\Repository\UserRepository;
use BikeShare\Test\Application\BikeSharingWebTestCase;
use Symfony\Component\HttpFoundation\Request;

class SmsDisabledRegistrationFlowTest extends BikeSharingWebTestCase
{
    private const SUPER_ADMIN_PHONE_NUMBER = '421951777777';

    private string $originalSmsConnector;

    protected function setUp(): void
    {
        $this->originalSmsConnector = $_ENV['SMS_CONNECTOR'];
        $_ENV['SMS_CONNECTOR'] = 'disabled';
        parent::setUp();
    }

    protected function tearDown(): void
    {
        $_ENV['SMS_CONNECTOR'] = $this->originalSmsConnector;
        parent::tearDown();
    }

    public function testAdminNotifiedRightAfterEmailConfirmWhenSmsDisabled(): void
    {
        $userEmail = 'test_sms_disabled_' . time() . '@example.com';
        $userPhone = '+421901' . rand(100000, 999999);

        $this->client->request(Request::METHOD_GET, '/register');
        $this->assertResponseIsSuccessful();

        $this->client->submitForm('register', [
            'registration_form[fullname]' => 'Jane Doe',
            'registration_form[city]' => 'Default City',
            'registration_form[useremail]' => $userEmail,
            'registration_form[password]' => 'password',
            'registration_form[password2]' => 'password',
            'registration_form[number]' => $userPhone,
            'registration_form[agree]' => '1',
        ]);
        $this->assertResponseRedirects('/');

        // After registration: only the email confirmation link.
        $emailsAfterRegister = static::getContainer()->get(MailSenderInterface::class)->getSentMessages();
        $this->assertCount(1, $emailsAfterRegister, 'Expected only the registration confirmation link');

        // Extract confirmation link.
        preg_match('/(\/user\/confirm\/email\/[a-z0-9]+)/', $emailsAfterRegister[0]['message'], $matches);
        $confirmationLink = $matches[1] ?? null;
        $this->assertNotNull($confirmationLink, 'Email confirmation link not found in registration email');

        // Confirm email.
        $this->client->request(Request::METHOD_GET, $confirmationLink);
        $this->assertResponseRedirects('/');

        // Capture emails BEFORE any next request (DebugMailSender resets between requests).
        $emailsAfterEmailConfirm = static::getContainer()->get(MailSenderInterface::class)->getSentMessages();

        // Email-confirm controller dispatches UserVerificationCompletedEvent;
        // listener with isSmsSystemEnabled=false notifies admins immediately, no phone step required.
        $this->assertCount(1, $emailsAfterEmailConfirm, 'Expected admin notification email after email confirmation');

        $userRepository = static::getContainer()->get(UserRepository::class);
        $registrationRepository = static::getContainer()->get(RegistrationRepository::class);

        $user = $userRepository->findItemByEmail($userEmail);
        $this->assertNotNull($user);
        $this->assertSame(0, (int)$user['isNumberConfirmed'], 'Phone confirmation should NOT happen when SMS disabled');
        $this->assertNull(
            $registrationRepository->findItemByUserId($user['userId']),
            'Registration row should be deleted after email confirmation'
        );

        $superAdmin = $userRepository->findItemByPhoneNumber(self::SUPER_ADMIN_PHONE_NUMBER);
        $adminEmail = $emailsAfterEmailConfirm[0];
        $this->assertSame($superAdmin['mail'], $adminEmail['recipient']);
        $this->assertStringContainsString($userEmail, $adminEmail['message']);
    }
}
