<?php

declare(strict_types=1);

namespace BikeShare\Test\Application\Controller;

use BikeShare\Db\DbInterface;
use BikeShare\SmsConnector\SmsConnectorInterface;
use PHPUnit\Framework\Constraint\Callback;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

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
        $client->request('GET', '/receive.php', [
            'number' => $phoneNumber,
            'message' => $message,
            'uuid' => $smsUuid,
            'time' => time(),
        ]);
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
        $client->request('GET', '/receive.php', [
            'number' => $phoneNumber,
            'message' => 'HELP',
            'uuid' => md5((string)microtime(true)),
            'time' => time(),
        ]);
        $this->assertResponseIsSuccessful();
        $this->assertSame('', $client->getResponse()->getContent());
        $smsConnector = $client->getContainer()->get(SmsConnectorInterface::class);

        $this->assertCount(1, $smsConnector->getSentMessages());
        dump($client->getContainer()->get('monolog.handler.test')->getRecords());
        $sentMessage = $smsConnector->getSentMessages()[0];
        foreach ($expectedCommands as $command) {
            $this->assertStringContainsString($command, $sentMessage);
        }
        foreach ($notExpectedCommands as $command) {
            $this->assertStringNotContainsString($command, $sentMessage);
        }
    }

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
}
