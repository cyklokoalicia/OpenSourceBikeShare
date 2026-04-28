<?php

declare(strict_types=1);

namespace BikeShare\Test\Application\Controller\SmsRequestController;

use BikeShare\Sms\DebugSmsSender;
use BikeShare\Test\Application\BikeSharingWebTestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Translation\TranslatableMessage;

class InfoCommandTest extends BikeSharingWebTestCase
{
    private const USER_PHONE_NUMBER = '421951111111';
    private const STAND_NAME = 'STAND1';

    public function testInfoCommand(): void
    {
        $this->client->request(
            Request::METHOD_GET,
            '/receive.php',
            [
                'number' => self::USER_PHONE_NUMBER,
                'message' => 'INFO ' . self::STAND_NAME,
                'uuid' => md5((string)microtime(true)),
                'time' => time(),
            ]
        );
        $this->assertResponseIsSuccessful();
        $this->assertSame('', $this->client->getResponse()->getContent());

        $smsSender = $this->client->getContainer()->get(DebugSmsSender::class);

        $this->assertCount(1, $smsSender->getSentMessages(), 'Invalid number of sent messages');
        $sentMessage = $smsSender->getSentMessages()[0];

        $this->assertSame(self::USER_PHONE_NUMBER, $sentMessage['number'], 'Invalid response sms number');
        $this->assertInstanceOf(TranslatableMessage::class, $sentMessage['message']);
        $this->assertSame('command.info.message', $sentMessage['message']->getMessage());
        $params = $sentMessage['message']->getParameters();
        $this->assertSame(self::STAND_NAME, $params['standName']);
        $this->assertArrayHasKey('description', $params);
        $this->assertArrayHasKey('hasGps', $params);
        $this->assertArrayHasKey('latitude', $params);
        $this->assertArrayHasKey('longitude', $params);
        $this->assertArrayHasKey('hasPhoto', $params);
        $this->assertArrayHasKey('photo', $params);
    }
}
