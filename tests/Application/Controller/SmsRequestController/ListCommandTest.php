<?php

declare(strict_types=1);

namespace Application\Controller\SmsRequestController;

use BikeShare\SmsConnector\SmsConnectorInterface;
use BikeShare\Test\Application\BikeSharingWebTestCase;
use Symfony\Component\HttpFoundation\Request;

class ListCommandTest extends BikeSharingWebTestCase
{
    private const ADMIN_PHONE_NUMBER = '421222222222';
    private const STAND_NAME = 'STAND1';

    private $forceStack;

    protected function setup(): void
    {
        parent::setup();
        $this->forceStack = $_ENV['FORCE_STACK'];
    }

    protected function tearDown(): void
    {
        $_ENV['FORCE_STACK'] = $this->forceStack;
        parent::tearDown();
    }

    /**
     * @dataProvider listCommandDataProvider
     */
    public function testListCommand(
        bool $forceStack
    ): void {
        $_ENV['FORCE_STACK'] = $forceStack ? '1' : '0';
        $this->client->request(
            Request::METHOD_GET,
            '/receive.php',
            [
                'number' => self::ADMIN_PHONE_NUMBER,
                'message' => 'LIST ' . self::STAND_NAME,
                'uuid' => md5((string)microtime(true)),
                'time' => time(),
            ]
        );
        $this->assertResponseIsSuccessful();
        $this->assertSame('', $this->client->getResponse()->getContent());

        $smsConnector = $this->client->getContainer()->get(SmsConnectorInterface::class);

        $this->assertCount(1, $smsConnector->getSentMessages(), 'Invalid number of sent messages');
        $sentMessage = $smsConnector->getSentMessages()[0];

        $this->assertSame(self::ADMIN_PHONE_NUMBER, $sentMessage['number'], 'Invalid response sms number');
        $this->assertStringStartsWith(
            'Bikes on stand ' . self::STAND_NAME . ':',
            $sentMessage['text'],
            'Invalid response sms text'
        );
        if ($forceStack) {
            $this->assertStringContainsString(
                '(first)',
                $sentMessage['text'],
                'There is no information about first bike on stand'
            );
        }
    }

    public function listCommandDataProvider(): iterable
    {
        yield 'force stack off' => [
            'forceStack' => false,
        ];
        yield 'force stack on' => [
            'forceStack' => true,
        ];
    }
}
