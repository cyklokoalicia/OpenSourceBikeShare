<?php

declare(strict_types=1);

namespace BikeShare\Test\Application\Flow;

use BikeShare\Mail\MailSenderInterface;
use BikeShare\Repository\RegistrationRepository;
use BikeShare\Repository\UserRepository;
use BikeShare\Test\Application\BikeSharingWebTestCase;
use BikeShare\Test\Support\MailAssert;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Translation\TranslatableMessage;

class SmsDisabledRegistrationFlowTest extends BikeSharingWebTestCase
{
    private const SUPER_ADMIN_PHONE_NUMBER = '421951777777';

    protected function setUp(): void
    {
        $this->setEnvVar('SMS_CONNECTOR', 'disabled');
        parent::setUp();
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
        $confirmationLink = MailAssert::confirmationLink($emailsAfterRegister[0], 'success.registration.mail');

        // Confirm email.
        $this->client->request(Request::METHOD_GET, $confirmationLink);
        $this->assertResponseRedirects('/');

        // Capture emails BEFORE any next request (DebugMailSender resets between requests).
        $emailsAfterEmailConfirm = static::getContainer()->get(MailSenderInterface::class)->getSentMessages();

        // Email-confirm controller dispatches UserVerificationCompletedEvent;
        // listener with isSmsSystemEnabled=false notifies admins immediately, no phone step required.

        $userRepository = static::getContainer()->get(UserRepository::class);
        $registrationRepository = static::getContainer()->get(RegistrationRepository::class);

        $user = $userRepository->findItemByEmail($userEmail);
        $this->assertNotNull($user);
        $this->assertSame(0, (int)$user['isNumberConfirmed'], 'Phone confirmation should NOT happen when SMS disabled');
        $this->assertNull(
            $registrationRepository->findItemByUserId($user['userId']),
            'Registration row should be deleted after email confirmation'
        );

        $adminMails = array_column(static::getContainer()->get(UserRepository::class)->findAdmins(), 'mail');
        $this->assertEqualsCanonicalizing($adminMails, array_column($emailsAfterEmailConfirm, 'recipient'));
        $superAdmin = $userRepository->findItemByPhoneNumber(self::SUPER_ADMIN_PHONE_NUMBER);
        $adminEmail = array_values(array_filter(
            $emailsAfterEmailConfirm,
            static fn (array $m): bool => $m['recipient'] === $superAdmin['mail']
        ))[0];
        $this->assertInstanceOf(TranslatableMessage::class, $adminEmail['message']);
        $this->assertSame('admin.notification.new_verified_user', $adminEmail['message']->getMessage());
        $this->assertSame($userEmail, $adminEmail['message']->getParameters()['email']);
    }
}
