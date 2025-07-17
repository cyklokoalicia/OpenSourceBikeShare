<?php

declare(strict_types=1);

namespace BikeShare\Test\Application\Controller\SmsRequestController;

use BikeShare\Credit\CreditSystemInterface;
use BikeShare\SmsConnector\SmsConnectorInterface;
use BikeShare\Test\Application\BikeSharingWebTestCase;
use Monolog\Logger;
use Symfony\Component\HttpFoundation\Request;

class CreditCommandTest extends BikeSharingWebTestCase
{
    private const USER_PHONE_NUMBER = '421111111111';

    private $smsSystemEnabled;

    protected function setup(): void
    {
        parent::setup();
        $this->smsSystemEnabled = $_ENV['CREDIT_SYSTEM_ENABLED'];
    }

    protected function tearDown(): void
    {
        $_ENV['CREDIT_SYSTEM_ENABLED'] = $this->smsSystemEnabled;
        parent::tearDown();
    }

    public function testCreditCommandWithEnabledSystem(): void
    {
        $_ENV['CREDIT_SYSTEM_ENABLED'] = '1';

        $this->client->request(
            Request::METHOD_GET,
            '/receive.php',
            [
                'number' => self::USER_PHONE_NUMBER,
                'message' => 'CREDIT',
                'uuid' => md5((string)microtime(true)),
                'time' => time(),
            ]
        );
        $this->assertResponseIsSuccessful();
        $this->assertSame('', $this->client->getResponse()->getContent());
        $smsConnector = $this->client->getContainer()->get(SmsConnectorInterface::class);

        $this->assertCount(1, $smsConnector->getSentMessages());
        $sentMessage = $smsConnector->getSentMessages()[0];

        $creditSystem = $this->client->getContainer()->get(CreditSystemInterface::class);

        $this->assertSame('Your remaining credit: 0€', $sentMessage['text'], 'Invalid response sms text');
        $this->assertStringEndsWith(
            $creditSystem->getCreditCurrency(),
            $sentMessage['text'],
            'Invalid response sms text currency'
        );
        $this->assertSame(self::USER_PHONE_NUMBER, $sentMessage['number'], 'Invalid response sms number');
    }

    public function testCreditCommandWithDisabledSystem(): void
    {
        $_ENV['CREDIT_SYSTEM_ENABLED'] = '0';

        $this->client->request(
            Request::METHOD_GET,
            '/receive.php',
            [
                'number' => self::USER_PHONE_NUMBER,
                'message' => 'CREDIT',
                'uuid' => md5((string)microtime(true)),
                'time' => time(),
            ]
        );
        $this->assertResponseIsSuccessful();
        $this->assertSame('', $this->client->getResponse()->getContent());
        $smsConnector = $this->client->getContainer()->get(SmsConnectorInterface::class);

        $this->assertCount(1, $smsConnector->getSentMessages());
        $sentMessage = $smsConnector->getSentMessages()[0];

        $creditSystem = $this->client->getContainer()->get(CreditSystemInterface::class);

        $this->assertSame(
            'Error. The command CREDIT does not exist. If you need help, send: HELP',
            $sentMessage['text'],
            'Invalid response sms text'
        );
        $this->assertSame(self::USER_PHONE_NUMBER, $sentMessage['number'], 'Invalid response sms recipient');
        $this->expectLog(Logger::WARNING, '/Validation error/');
    }
}
