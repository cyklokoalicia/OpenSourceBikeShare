<?php

declare(strict_types=1);

namespace BikeShare\Test\Application\Controller\SmsRequestController;

use PHPUnit\Framework\Attributes\DataProvider;
use BikeShare\Sms\DebugSmsSender;
use BikeShare\Test\Application\BikeSharingWebTestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Translation\TranslatableMessage;

class ListCommandTest extends BikeSharingWebTestCase
{
    private const ADMIN_PHONE_NUMBER = '421951222222';
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

    #[DataProvider('listCommandDataProvider')]
    public function testListCommand(
        bool $forceStack
    ): void {
        $_ENV['FORCE_STACK'] = $forceStack ? '1' : '0';
        $this->client->request(
            Request::METHOD_GET,
            '/receive.php',
            [
                'number' => self::ADMIN_PHONE_NUMBER,
                'message' => 'FORCERETURN 1 ' . self::STAND_NAME,
                'uuid' => md5((string)microtime(true)),
                'time' => time(),
            ]
        );
        $this->client->request(
            Request::METHOD_GET,
            '/receive.php',
            [
                'number' => self::ADMIN_PHONE_NUMBER,
                'message' => 'FORCERETURN 2 ' . self::STAND_NAME,
                'uuid' => md5((string)microtime(true)),
                'time' => time(),
            ]
        );
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

        $smsSender = $this->client->getContainer()->get(DebugSmsSender::class);

        $this->assertCount(1, $smsSender->getSentMessages(), 'Invalid number of sent messages');
        $sentMessage = $smsSender->getSentMessages()[0];

        $this->assertSame(self::ADMIN_PHONE_NUMBER, $sentMessage['number'], 'Invalid response sms number');
        $this->assertInstanceOf(TranslatableMessage::class, $sentMessage['message']);
        $this->assertSame('command.list.bikes', $sentMessage['message']->getMessage());
        $params = $sentMessage['message']->getParameters();
        $this->assertSame(self::STAND_NAME, $params['standName']);
        if ($forceStack) {
            $this->assertSame('true', $params['hasFirstBike']);
            $this->assertNotEmpty($params['firstBike']);
        } else {
            $this->assertSame('false', $params['hasFirstBike']);
            $this->assertSame('', $params['firstBike']);
        }
        $this->assertArrayHasKey('otherBikes', $params);
    }

    public static function listCommandDataProvider(): iterable
    {
        yield 'force stack off' => [
            'forceStack' => false,
        ];
        yield 'force stack on' => [
            'forceStack' => true,
        ];
    }
}
