<?php

declare(strict_types=1);

namespace BikeShare\Test\Application\Controller;

use BikeShare\Db\DbInterface;
use BikeShare\Event\UserRegistrationEvent;
use BikeShare\Mail\MailSenderInterface;
use BikeShare\Repository\CreditRepository;
use BikeShare\Repository\RegistrationRepository;
use BikeShare\Repository\UserRepository;
use BikeShare\SmsConnector\SmsConnectorInterface;
use PHPUnit\Framework\Constraint\Callback;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Request;

class SmsRequestControllerTest extends WebTestCase
{
    private const USER_PHONE_NUMBER = '421111111111';
    private const ADMIN_PHONE_NUMBER = '421222222222';

    protected function setup(): void
    {
        parent::tearDown();
        $this->smsSystemEnabled = $_ENV['CREDIT_SYSTEM_ENABLED'];
    }

    protected function tearDown(): void
    {
        $_ENV['CREDIT_SYSTEM_ENABLED'] = $this->smsSystemEnabled;
        parent::tearDown();
    }

    /**
     * @var Callback|string $expectedSms
     * @dataProvider smsDataProvider
     */
    public function testBaseSmsFlow(
        string $phoneNumber,
        string $message,
        string $expectedResponse,
        $expectedSms
    ): void {
        $smsUuid = md5((string)microtime(true));
        $client = static::createClient();
        $client->request(
            Request::METHOD_GET,
            '/receive.php',
            [
                'number' => $phoneNumber,
                'message' => $message,
                'uuid' => $smsUuid,
                'time' => time(),
            ]
        );
        $this->assertResponseIsSuccessful();
        $this->assertSame($expectedResponse, $client->getResponse()->getContent());

        $smsConnector = $client->getContainer()->get(SmsConnectorInterface::class);

        if (is_null($expectedSms)) {
            $this->assertCount(0, $smsConnector->getSentMessages());
        } else {
            $this->assertCount(1, $smsConnector->getSentMessages());
            if (is_string($expectedSms)) {
                $this->assertSame($expectedSms, $smsConnector->getSentMessages()[0]);
            } else {
                $this->assertThat($smsConnector->getSentMessages()[0], $expectedSms);
            }
        }

        $db = $client->getContainer()->get(DbInterface::class);
        $receivedSms = $db->query('SELECT * FROM received WHERE sms_uuid = ?', [$smsUuid])->fetchAllAssoc();
        $this->assertCount(1, $receivedSms);
        $this->assertSame($phoneNumber, $receivedSms[0]['sender']);
        $this->assertSame($message, $receivedSms[0]['sms_text']);
        $this->assertSame($smsUuid, $receivedSms[0]['sms_uuid']);
    }

    public function smsDataProvider(): iterable
    {
        yield 'unknown user' => [
            'phoneNumber' => '0000000000000',
            'message' => 'Test message',
            'expectedResponse' => 'User not found',
            'expectedSms' => null,
        ];
        yield 'invalid message' => [
            'phoneNumber' => self::USER_PHONE_NUMBER,
            'message' => 'Test message',
            'expectedResponse' => '',
            'expectedSms' => null,
        ];
        yield 'not full command' => [
            'phoneNumber' => self::USER_PHONE_NUMBER,
            'message' => 'RENT',
            'expectedResponse' => '',
            'expectedSms' => 'Error. More arguments needed, use command with bike number: RENT 42',
        ];
        yield 'full command' => [
            'phoneNumber' => self::USER_PHONE_NUMBER,
            'message' => 'HELP',
            'expectedResponse' => '',
            'expectedSms' => $this->callback(function ($message) {
                return (bool)preg_match('/Commands:.*/', $message);
            })
        ];
        yield 'full command with param' => [
            'phoneNumber' => self::USER_PHONE_NUMBER,
            'message' => 'WHERE 1',
            'expectedResponse' => '',
            'expectedSms' => $this->callback(function ($message) {
                return (bool)preg_match('/Bike 1 is at stand Stand \w*. /', $message);
            })
        ];
        yield 'invalid privileges' => [
            'phoneNumber' => self::USER_PHONE_NUMBER,
            'message' => 'FORCERENT 1',
            'expectedResponse' => '',
            'expectedSms' => 'Sorry, this command is only available for the privileged users.',
        ];
    }

    /**
     * @dataProvider smsHelpDataProvider
     */
    public function testHelpCommand(
        string $phoneNumber,
        array $expectedCommands,
        array $notExpectedCommands,
        bool $isCreditSystemEnabled
    ): void {
        $_ENV['CREDIT_SYSTEM_ENABLED'] = $isCreditSystemEnabled ? '1' : '0';
        $client = static::createClient();
        $client->request(
            Request::METHOD_GET,
            '/receive.php',
            [
                'number' => $phoneNumber,
                'message' => 'HELP',
                'uuid' => md5((string)microtime(true)),
                'time' => time(),
            ]
        );
        $this->assertResponseIsSuccessful();
        $this->assertSame('', $client->getResponse()->getContent());
        $smsConnector = $client->getContainer()->get(SmsConnectorInterface::class);

        $this->assertCount(1, $smsConnector->getSentMessages());
        $sentMessage = $smsConnector->getSentMessages()[0];
        foreach ($expectedCommands as $command) {
            $this->assertStringContainsString($command, $sentMessage);
        }
        foreach ($notExpectedCommands as $command) {
            $this->assertStringNotContainsString($command, $sentMessage);
        }
    }

    /**
     * @phpcs:disable Generic.Files.LineLength
     */
    public function smsHelpDataProvider(): iterable
    {
        yield 'user help' => [
            'phoneNumber' => self::USER_PHONE_NUMBER,
            'expectedCommands' => ['HELP', 'FREE', 'RENT', 'RETURN', 'WHERE', 'INFO', 'NOTE'],
            'notExpectedCommands' => ['FORCERENT', 'FORCERETURN', 'LIST', 'LAST', 'REVERT', 'ADD', 'DELNOTE', 'DELNOTE', 'TAG', 'UNTAG', 'CREDIT'],
            'isCreditSystemEnabled' => false,
        ];
        yield 'admin help' => [
            'phoneNumber' => self::ADMIN_PHONE_NUMBER,
            'expectedCommands' => ['HELP', 'FREE', 'RENT', 'RETURN', 'WHERE', 'INFO', 'NOTE', 'FORCERENT', 'FORCERETURN', 'LIST', 'LAST', 'REVERT', 'ADD', 'DELNOTE', 'DELNOTE', 'TAG', 'UNTAG'],
            'notExpectedCommands' => ['CREDIT'],
            'isCreditSystemEnabled' => false,
        ];
        yield 'user credit help' => [
            'phoneNumber' => self::USER_PHONE_NUMBER,
            'expectedCommands' => ['HELP', 'FREE', 'RENT', 'RETURN', 'WHERE', 'INFO', 'NOTE', 'CREDIT'],
            'notExpectedCommands' => ['FORCERENT', 'FORCERETURN', 'LIST', 'LAST', 'REVERT', 'ADD', 'DELNOTE', 'DELNOTE', 'TAG', 'UNTAG'],
            'isCreditSystemEnabled' => true,
        ];
        yield 'admin credit help' => [
            'phoneNumber' => self::ADMIN_PHONE_NUMBER,
            'expectedCommands' => ['HELP', 'FREE', 'RENT', 'RETURN', 'WHERE', 'INFO', 'NOTE', 'FORCERENT', 'FORCERETURN', 'LIST', 'LAST', 'REVERT', 'ADD', 'DELNOTE', 'DELNOTE', 'TAG', 'UNTAG', 'CREDIT'],
            'notExpectedCommands' => [],
            'isCreditSystemEnabled' => true,
        ];
    }

    /**
     * This test generates a new user on each run, so be careful with running it multiple times.
     * Better to run a command `php bin/console load:fixtures` before running this test.
     */
    public function testSuccessAddCommand(): void
    {
        $adminPhoneNumber = self::ADMIN_PHONE_NUMBER;
        $email = 'testAddUser' . rand(1000, 9999) . '@net.net';
        $phoneNumber = '42199999' . rand(1000, 9999);
        $fullName = 'Test User';

        $client = static::createClient();
        $client->request(
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
        $this->assertSame('', $client->getResponse()->getContent());
        $smsConnector = $client->getContainer()->get(SmsConnectorInterface::class);

        $this->assertCount(1, $smsConnector->getSentMessages());
        $sentMessage = $smsConnector->getSentMessages()[0];

        $this->assertSame(
            'User ' . $fullName . ' added. They need to read email and agree to rules before using the system.',
            $sentMessage,
            'User was not added'
        );

        $adminUser = $client->getContainer()->get(UserRepository::class)->findItemByPhoneNumber($adminPhoneNumber);
        $newUser = $client->getContainer()->get(UserRepository::class)->findItemByPhoneNumber($phoneNumber);

        $this->assertNotEmpty($newUser, 'User was not added');
        $this->assertSame($email, $newUser['mail']);
        $this->assertSame($phoneNumber, $newUser['number']);
        $this->assertSame($fullName, $newUser['username']);
        $this->assertSame(0, $newUser['privileges']);
        $this->assertSame(0, $newUser['isNumberConfirmed']);
        $this->assertSame(0, $newUser['userLimit']);
        # Assert that the new user has the same city as the admin user who added them
        $this->assertSame($adminUser['city'], $newUser['city']);

        $userCredits = $client->getContainer()->get(CreditRepository::class)->findItem($newUser['userId']);
        $this->assertSame(0.0, $userCredits, 'User credits were not initialized to 0.0');

        # Assert that the event UserRegistrationEvent was dispatched
        $calledListeners = $client->getContainer()->get('event_dispatcher')->getCalledListeners();
        $userRegistrationEvent = null;
        foreach ($calledListeners as $listener) {
            if ($listener['event'] === UserRegistrationEvent::class) {
                $userRegistrationEvent = $listener;
                break;
            }
        }
        $this->assertNotNull($userRegistrationEvent, 'UserRegistrationEvent was not dispatched.');

        # Assert that the confirmation email was sent with the correct content
        $sentMessages = $client->getContainer()->get(MailSenderInterface::class)->getSentMessages();
        $this->assertCount(1, $sentMessages);
        $names = preg_split("/[\s,]+/", $fullName);
        $firstName = $names[0];
        # Assert that the email contains only the first name of the user
        $this->assertStringContainsString(
            'Hello ' . $firstName . PHP_EOL,
            $sentMessages[0]['message']
        );
        # Assert that the email contains the link to the system rules page
        $this->assertStringContainsString(
            $client->getServerParameter('SYSTEM_RULES'),
            $sentMessages[0]['message']
        );

        $registrationKey = $client->getContainer()
            ->get(RegistrationRepository::class)->findItemByUserId($newUser['userId'])['userKey'] ?? null;
        $this->assertNotEmpty($registrationKey);
        # Assert that the email contains the registration key for the user
        $this->assertStringContainsString(
            $registrationKey,
            $sentMessages[0]['message']
        );
    }
}
