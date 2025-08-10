<?php

declare(strict_types=1);

namespace BikeShare\Test\Application\Controller;

use BikeShare\Db\DbInterface;
use BikeShare\SmsConnector\SmsConnectorInterface;
use BikeShare\Test\Application\BikeSharingWebTestCase;
use Monolog\Logger;
use PHPUnit\Framework\Constraint\Callback;
use Symfony\Component\HttpFoundation\Request;

class SmsRequestControllerTest extends BikeSharingWebTestCase
{
    private const USER_PHONE_NUMBER = '421951111111';

    /**
     * @var Callback|string $expectedSms
     * @dataProvider smsDataProvider
     */
    public function testBaseSmsFlow(
        string $phoneNumber,
        string $message,
        string $expectedResponse,
        $expectedSms,
        array $expectedLog
    ): void {
        $smsUuid = md5((string)microtime(true));

        $this->client->request(
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
        $this->assertSame($expectedResponse, $this->client->getResponse()->getContent());

        $smsConnector = $this->client->getContainer()->get(SmsConnectorInterface::class);

        if (is_null($expectedSms)) {
            $this->assertCount(0, $smsConnector->getSentMessages());
        } else {
            $this->assertCount(1, $smsConnector->getSentMessages());
            if (is_string($expectedSms)) {
                $this->assertSame($expectedSms, $smsConnector->getSentMessages()[0]['text']);
            } else {
                $this->assertThat($smsConnector->getSentMessages()[0]['text'], $expectedSms);
            }
        }

        $db = $this->client->getContainer()->get(DbInterface::class);
        $receivedSms = $db->query('SELECT * FROM received WHERE sms_uuid = ?', [$smsUuid])->fetchAllAssoc();
        $this->assertCount(1, $receivedSms);
        $this->assertSame($phoneNumber, $receivedSms[0]['sender']);
        $this->assertSame($message, $receivedSms[0]['sms_text']);
        $this->assertSame($smsUuid, $receivedSms[0]['sms_uuid']);

        if (!empty($expectedLog)) {
            $this->expectLog(...$expectedLog);
        }
    }

    public function smsDataProvider(): iterable
    {
        yield 'invalid phone number' => [
            'phoneNumber' => '0000000000000',
            'message' => 'Test message',
            'expectedResponse' => 'Invalid phone number',
            'expectedSms' => null,
            'expectedLog' => [
                Logger::ERROR, '/Invalid phone number/',
            ],
        ];
        yield 'unknown user' => [
            'phoneNumber' => '421951000000',
            'message' => 'Test message',
            'expectedResponse' => 'User not found',
            'expectedSms' => null,
            'expectedLog' => [
                Logger::ERROR, '/User not found/',
            ],
        ];
        yield 'invalid message' => [
            'phoneNumber' => self::USER_PHONE_NUMBER,
            'message' => 'Test message',
            'expectedResponse' => '',
            'expectedSms' => null,
            'expectedLog' => [
                Logger::ERROR, '/Error processing SMS/',
            ],
        ];
        yield 'not full command' => [
            'phoneNumber' => self::USER_PHONE_NUMBER,
            'message' => 'RENT',
            'expectedResponse' => '',
            'expectedSms' => 'Error. More arguments needed, use command with bike number: RENT 42',
            'expectedLog' => [],
        ];
        yield 'full command' => [
            'phoneNumber' => self::USER_PHONE_NUMBER,
            'message' => 'HELP',
            'expectedResponse' => '',
            'expectedSms' => $this->callback(function ($message) {
                return (bool)preg_match('/Commands:.*/', $message);
            }),
            'expectedLog' => [],
        ];
        yield 'full command with param' => [
            'phoneNumber' => self::USER_PHONE_NUMBER,
            'message' => 'WHERE 1',
            'expectedResponse' => '',
            'expectedSms' => $this->callback(function ($message) {
                return (bool)preg_match('/Bike 1 is at stand STAND\d*. /', $message);
            }),
            'expectedLog' => [],
        ];
        yield 'invalid privileges' => [
            'phoneNumber' => self::USER_PHONE_NUMBER,
            'message' => 'FORCERENT 1',
            'expectedResponse' => '',
            'expectedSms' => 'Sorry, this command is only available for the privileged users.',
            'expectedLog' => [
                Logger::WARNING, '/Validation error/',
            ],
        ];
    }
}
