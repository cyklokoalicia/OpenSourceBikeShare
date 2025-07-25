<?php

declare(strict_types=1);

namespace BikeShare\Test\Application\Controller\SmsRequestController;

use BikeShare\Credit\CreditSystemInterface;
use BikeShare\Event\UserRegistrationEvent;
use BikeShare\Mail\MailSenderInterface;
use BikeShare\Repository\RegistrationRepository;
use BikeShare\Repository\UserRepository;
use BikeShare\SmsConnector\SmsConnectorInterface;
use BikeShare\Test\Application\BikeSharingWebTestCase;
use Symfony\Component\HttpFoundation\Request;

class AddCommandTest extends BikeSharingWebTestCase
{
    private const ADMIN_PHONE_NUMBER = '421222222222';

    /**
     * This test generates a new user on each run, so be careful with running it multiple times.
     * Better to run a command `php bin/console load:fixtures` before running this test.
     */
    public function testAddCommand(): void
    {
        $adminPhoneNumber = self::ADMIN_PHONE_NUMBER;
        $email = 'testAddUser' . rand(1000, 9999) . '@net.net';
        $phoneNumber = '42199999' . rand(1000, 9999);
        $fullName = 'Test User';

        $this->client->getContainer()->get('event_dispatcher')->addListener(
            UserRegistrationEvent::class,
            function (UserRegistrationEvent $event) use ($email, $phoneNumber, $fullName) {
                $this->assertSame($email, $event->getUser()->getEmail(), 'Invalid user email');
                $this->assertSame($phoneNumber, $event->getUser()->getNumber(), 'Invalid user number');
                $this->assertSame($fullName, $event->getUser()->getUsername(), 'Invalid user name');
            }
        );

        $this->client->request(
            Request::METHOD_GET,
            '/receive.php',
            [
                'number' => $adminPhoneNumber,
                'message' => 'ADD ' . $email . ' ' . $phoneNumber . ' ' . $fullName,
                'uuid' => md5((string)microtime(true)),
                'time' => time(),
            ]
        );
        $this->assertResponseIsSuccessful();
        $this->assertSame('', $this->client->getResponse()->getContent());
        $smsConnector = $this->client->getContainer()->get(SmsConnectorInterface::class);

        $this->assertCount(1, $smsConnector->getSentMessages());
        $sentMessage = $smsConnector->getSentMessages()[0];

        $this->assertSame(
            'User ' . $fullName . ' added. They need to read email and agree to rules before using the system.',
            $sentMessage['text'],
            'User was not added'
        );
        $this->assertSame(
            self::ADMIN_PHONE_NUMBER,
            $sentMessage['number'],
            'Sms was not sent to the admin user'
        );

        $userRepository = $this->client->getContainer()->get(UserRepository::class);
        $adminUser = $userRepository->findItemByPhoneNumber($adminPhoneNumber);
        $newUser = $userRepository->findItemByPhoneNumber($phoneNumber);

        $this->assertNotEmpty($newUser, 'User was not added');
        $this->assertSame($email, $newUser['mail']);
        $this->assertSame($phoneNumber, $newUser['number']);
        $this->assertSame($fullName, $newUser['username']);
        $this->assertSame(0, $newUser['privileges']);
        $this->assertSame(0, $newUser['isNumberConfirmed']);
        $this->assertSame(0, $newUser['userLimit']);
        # Assert that the new user has the same city as the admin user who added them
        $this->assertSame($adminUser['city'], $newUser['city']);

        $userCredits = $this->client->getContainer()
            ->get(CreditSystemInterface::class)->getUserCredit($newUser['userId']);
        $this->assertSame(0.0, $userCredits, 'User credits were not initialized to 0.0');

        # Assert that the confirmation email was sent with the correct content
        $sentMessages = $this->client->getContainer()->get(MailSenderInterface::class)->getSentMessages();
        $this->assertCount(1, $sentMessages, 'Invalid number of sent messages');
        $names = preg_split("/[\s,]+/", $fullName);
        $firstName = $names[0];
        # Assert that the email contains only the first name of the user
        $this->assertStringContainsString(
            'Hello ' . $firstName . PHP_EOL,
            $sentMessages[0]['message'],
            'Email does not contain the user\'s first name'
        );
        # Assert that the email contains the link to the system rules page
        $this->assertStringContainsString(
            $this->client->getServerParameter('SYSTEM_RULES'),
            $sentMessages[0]['message'],
            'Email does not contain the system rules link'
        );

        $registrationKey = $this->client->getContainer()
            ->get(RegistrationRepository::class)->findItemByUserId($newUser['userId'])['userKey'] ?? null;
        $this->assertNotEmpty($registrationKey);
        # Assert that the email contains the registration key for the user
        $this->assertStringContainsString(
            $registrationKey,
            $sentMessages[0]['message'],
            'Email does not contain the registration key'
        );

        $notCalledListeners = $this->client->getContainer()->get('event_dispatcher')->getNotCalledListeners();
        foreach ($notCalledListeners as $listener) {
            if ($listener['pretty'] === 'BikeShare\EventListener\RegistrationEventListener::__invoke') {
                $this->fail('Registration event listener was not called');
            }
            if ($listener['stub'] === 'closure(UserRegistrationEvent $event)') {
                $this->fail('TestEventListener was not called');
            }
        };
    }
}
