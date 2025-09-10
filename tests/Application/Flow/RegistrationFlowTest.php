<?php

declare(strict_types=1);

namespace BikeShare\Test\Application\Flow;

use BikeShare\Mail\MailSenderInterface;
use BikeShare\Purifier\PhonePurifierInterface;
use BikeShare\Repository\RegistrationRepository;
use BikeShare\Repository\UserRepository;
use BikeShare\SmsConnector\SmsConnectorInterface;
use BikeShare\Test\Application\BikeSharingWebTestCase;
use Symfony\Component\HttpFoundation\Request;

class RegistrationFlowTest extends BikeSharingWebTestCase
{
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
        $smsConnector = $this->client->getContainer()->get(SmsConnectorInterface::class);
        $phonePurifier = $this->client->getContainer()->get(PhonePurifierInterface::class);
        $this->assertCount(1, $smsConnector->getSentMessages(), 'More than one SMS was sent');
        $sentMessages = $smsConnector->getSentMessages()[0];
        $this->assertSame($phonePurifier->purify($userPhone), $sentMessages['number'], 'Invalid phone number');
        preg_match('/Enter this code to verify your phone: ([A-Z]{2} \d*)/', $sentMessages['text'], $matches);
        $smsCode = str_replace(' ', '', $matches[1] ?? '');
        $this->assertNotEmpty($smsCode, 'SMS code not found in the sent message');

        $this->assertResponseRedirects('/user/confirm/phone');
        $this->client->followRedirect();
        $this->assertResponseIsSuccessful();

        // Step 2: Submit code
        $this->client->submitForm('formSubmit', [
            'form[smscode]' => $smsCode,
        ]);
        $this->assertResponseRedirects();
        $this->client->followRedirect();
        $this->assertRouteSame('home');

        // Assert user is updated in the database
        $user = $userRepository->findItemByEmail($userEmail);
        $this->assertNotNull($user);
        $this->assertSame(1, $user['isNumberConfirmed'], 'User phone number is not confirmed');
    }
}
