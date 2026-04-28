<?php

declare(strict_types=1);

namespace BikeShare\Test\Application\Controller\SmsRequestController;

use BikeShare\Credit\CreditSystemInterface;
use BikeShare\Sms\DebugSmsSender;
use BikeShare\Test\Application\BikeSharingWebTestCase;
use Monolog\Logger;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Translation\TranslatableMessage;

class CreditCommandTest extends BikeSharingWebTestCase
{
    private const USER_PHONE_NUMBER = '421951111111';

    private $creditSystemEnabled;

    protected function setup(): void
    {
        parent::setup();
        $this->creditSystemEnabled = $_ENV['CREDIT_SYSTEM_ENABLED'];
    }

    protected function tearDown(): void
    {
        $_ENV['CREDIT_SYSTEM_ENABLED'] = $this->creditSystemEnabled;
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

        $smsSender = $this->client->getContainer()->get(DebugSmsSender::class);
        $this->assertCount(1, $smsSender->getSentMessages());
        $sentMessage = $smsSender->getSentMessages()[0];

        $creditSystem = $this->client->getContainer()->get(CreditSystemInterface::class);

        $this->assertInstanceOf(TranslatableMessage::class, $sentMessage['message']);
        $this->assertSame('command.credit.message', $sentMessage['message']->getMessage());
        $this->assertSame(
            [
                'credit' => 0.0,
                'creditCurrency' => $creditSystem->getCreditCurrency(),
            ],
            $sentMessage['message']->getParameters()
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

        $smsSender = $this->client->getContainer()->get(DebugSmsSender::class);
        $this->assertCount(1, $smsSender->getSentMessages());
        $sentMessage = $smsSender->getSentMessages()[0];

        $this->assertInstanceOf(TranslatableMessage::class, $sentMessage['message']);
        $this->assertSame('command.error.unknown_command', $sentMessage['message']->getMessage());
        $this->assertSame(
            ['badCommand' => 'CREDIT', 'helpCommand' => 'HELP'],
            $sentMessage['message']->getParameters()
        );
        $this->assertSame(self::USER_PHONE_NUMBER, $sentMessage['number'], 'Invalid response sms recipient');
        $this->expectLog(Logger::WARNING, '/Validation error/');
    }
}
