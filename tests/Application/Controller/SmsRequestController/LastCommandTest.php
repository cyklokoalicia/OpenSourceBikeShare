<?php

declare(strict_types=1);

namespace BikeShare\Test\Application\Controller\SmsRequestController;

use BikeShare\SmsConnector\SmsConnectorInterface;
use BikeShare\Test\Application\BikeSharingWebTestCase;
use Symfony\Component\HttpFoundation\Request;

class LastCommandTest extends BikeSharingWebTestCase
{
    private const ADMIN_PHONE_NUMBER = '421222222222';
    private const BIKE_NUMBER = 1;

    public function testLastCommand(): void
    {
        $this->client->request(
            Request::METHOD_GET,
            '/receive.php',
            [
                'number' => self::ADMIN_PHONE_NUMBER,
                'message' => 'LAST ' . self::BIKE_NUMBER,
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
        $this->assertStringStartsWith('B.1:', $sentMessage['text'], 'Invalid response sms text');
    }
}
