<?php

declare(strict_types=1);

namespace BikeShare\Test\Application\Flow;

use BikeShare\Mail\MailSenderInterface;
use BikeShare\Purifier\PhonePurifierInterface;
use BikeShare\Repository\RegistrationRepository;
use BikeShare\Repository\UserRepository;
use BikeShare\Sms\DebugSmsSender;
use BikeShare\Test\Application\BikeSharingWebTestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Translation\TranslatableMessage;

class RegistrationFlowTest extends BikeSharingWebTestCase
{
    private const SUPER_ADMIN_PHONE_NUMBER = '421951777777';

    public function testFullRegistrationFlow(): void
    {
        $userEmail = 'test_' . time() . '@example.com';
        $userPhone = '+421901' . rand(100000, 999999);

        $this->client->request(Request::METHOD_GET, '/register');
        $this->assertResponseIsSuccessful();

        $this->client->submitForm(
            'register',
            [
                'registration_form[fullname]' => 'John Doe',
                'registration_form[city]' => 'Default City',
                'registration_form[useremail]' => $userEmail,
                'registration_form[password]' => 'password',
                'registration_form[password2]' => 'password',
                'registration_form[number]' => $userPhone,
                'registration_form[agree]' => '1',
            ]
        );

        $this->assertResponseRedirects('/');

        $mailSender = static::getContainer()->get(MailSenderInterface::class);
        $sendEmails = $mailSender->getSentMessages();
        $this->assertCount(1, $sendEmails, 'More than one email was sent');
        $email = $sendEmails[0];

        // Extract confirmation link
        $body = $email['message'];
        $this->assertNotNull($body);
        preg_match('/(\/user\/confirm\/email\/[a-z0-9]+)/', $body, $matches);
        $confirmationLink = $matches[1];

        $this->client->request(Request::METHOD_GET, $confirmationLink);
        $this->assertResponseRedirects('/');
        $this->client->followRedirect();
        $this->assertResponseRedirects('/login');
        $this->client->followRedirect();

        // Assert user is updated in the database
        $userRepository = static::getContainer()->get(UserRepository::class);
        $registrationRepository = static::getContainer()->get(RegistrationRepository::class);
        $user = $userRepository->findItemByEmail($userEmail);
        $confirmation = $registrationRepository->findItemByUserId($user['userId']);
        $this->assertNull($confirmation);

        // Login the user
        $this->client->submitForm('login', [
            'number' => $userPhone,
            'password' => 'password',
        ]);
        $this->assertResponseRedirects('/');
        $this->client->followRedirect();
        $this->assertResponseRedirects('/user/confirm/phone');
        $this->client->followRedirect();
        $this->assertResponseIsSuccessful();
        $this->assertRouteSame('user_confirm_phone');

        // Step 1: Trigger SMS
        $this->client->submitForm('formSubmit');

        // Get SMS code from history
        $smsSender = $this->client->getContainer()->get(DebugSmsSender::class);
        $phonePurifier = $this->client->getContainer()->get(PhonePurifierInterface::class);
        $this->assertCount(1, $smsSender->getSentMessages(), 'More than one SMS was sent');
        $sentMessages = $smsSender->getSentMessages()[0];
        $this->assertSame($phonePurifier->purify($userPhone), $sentMessages['number'], 'Invalid phone number');
        $this->assertInstanceOf(TranslatableMessage::class, $sentMessages['message']);
        $this->assertSame(
            'user.phone_confirm.sms_code',
            $sentMessages['message']->getMessage()
        );
        $smsCodeRaw = $sentMessages['message']->getParameters()['smsCode'] ?? '';
        $this->assertMatchesRegularExpression('/^[A-Z]{2} \d+$/', $smsCodeRaw);
        $smsCode = str_replace(' ', '', $smsCodeRaw);
        $this->assertNotEmpty($smsCode, 'SMS code not found in the sent message');

        $this->assertResponseRedirects('/user/confirm/phone');
        $this->client->followRedirect();
        $this->assertResponseIsSuccessful();

        // Step 2: Submit code
        $this->client->submitForm('formSubmit', [
            'form[smscode]' => $smsCode,
        ]);
        $this->assertResponseRedirects();

        // Re-fetch mail sender — kernel reboots between requests, replacing the DebugMailSender instance.
        // Capture BEFORE followRedirect (next request resets the buffer).
        $emailsAfterPhoneConfirm = static::getContainer()->get(MailSenderInterface::class)->getSentMessages();

        $this->client->followRedirect();
        $this->assertRouteSame('home');

        // Assert user is updated in the database
        $user = $userRepository->findItemByEmail($userEmail);
        $this->assertNotNull($user);
        $this->assertSame(1, $user['isNumberConfirmed'], 'User phone number is not confirmed');

        // Admins were notified about the newly fully-verified user.
        $this->assertCount(
            1,
            $emailsAfterPhoneConfirm,
            'Expected one admin notification email after phone confirmation'
        );

        $superAdmin = $userRepository->findItemByPhoneNumber(self::SUPER_ADMIN_PHONE_NUMBER);
        $adminEmail = $emailsAfterPhoneConfirm[0];
        $this->assertSame($superAdmin['mail'], $adminEmail['recipient']);
        $this->assertStringContainsString($userEmail, $adminEmail['message']);
        $this->assertStringContainsString($phonePurifier->purify($userPhone), $adminEmail['message']);
    }
}
