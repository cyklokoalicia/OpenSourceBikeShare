<?php

declare(strict_types=1);

namespace BikeShare\Test\Application\Controller\SmsRequestController;

use BikeShare\Db\DbInterface;
use BikeShare\Event\BikeReturnEvent;
use BikeShare\Repository\BikeRepository;
use BikeShare\Repository\StandRepository;
use BikeShare\Repository\UserRepository;
use BikeShare\SmsConnector\SmsConnectorInterface;
use BikeShare\Test\Application\BikeSharingWebTestCase;
use Symfony\Component\HttpFoundation\Request;

class ForceReturnCommandTest extends BikeSharingWebTestCase
{
    private const ADMIN_PHONE_NUMBER = '421222222222';
    private const BIKE_NUMBER = 3;
    private const STAND_NAME = 'STAND3';

    public function testForceReturnCommand(): void
    {
        $user = $this->client->getContainer()->get(UserRepository::class)
            ->findItemByPhoneNumber(self::ADMIN_PHONE_NUMBER);

        $this->client->getContainer()->get('event_dispatcher')->addListener(
            BikeReturnEvent::class,
            function (BikeReturnEvent $event) use ($user) {
                $this->assertSame(self::BIKE_NUMBER, $event->getBikeNumber(), 'Invalid bike number');
                $this->assertSame(self::STAND_NAME, $event->getStandName(), 'Invalid stand name');
                $this->assertSame(true, $event->isForce());
                $this->assertSame($user['userId'], $event->getUserId());
            }
        );

        $this->client->request(
            Request::METHOD_GET,
            '/receive.php',
            [
                'number' => self::ADMIN_PHONE_NUMBER,
                'message' => 'FORCERETURN ' . self::BIKE_NUMBER . ' ' . self::STAND_NAME,
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
        $this->assertMatchesRegularExpression(
            '/Bike ' . self::BIKE_NUMBER . ' returned to stand ' . self::STAND_NAME . ' : Lock with code \d{4}\.' .
                'Please, rotate the lockpad to 0000 when leaving\.Wipe the bike clean if it is dirty, please\./',
            $sentMessage['text'],
            'Invalid response sms text'
        );

        $bike = $this->client->getContainer()->get(BikeRepository::class)->findItem(self::BIKE_NUMBER);
        $stand = $this->client->getContainer()->get(StandRepository::class)->findItemByName(self::STAND_NAME);

        $this->assertEquals(self::STAND_NAME, $bike['standName'], 'Bike is not on stand');
        $this->assertNull($bike['userId'], 'Bike is still rented');

        $history = $this->client->getContainer()->get(DbInterface::class)->query(
            'SELECT * FROM history WHERE userId = :userId AND bikeNum = :bikeNum ORDER BY id DESC LIMIT 1',
            [
                'userId' => $user['userId'],
                'bikeNum' => self::BIKE_NUMBER,
            ]
        )->fetchAssoc();

        $this->assertSame($history['action'], 'FORCERETURN', 'Invalid history action');
        $this->assertEquals($stand['standId'], $history['parameter'], 'Missed standId');

        $notCalledListeners = $this->client->getContainer()->get('event_dispatcher')->getNotCalledListeners();
        foreach ($notCalledListeners as $listener) {
            if ($listener['stub'] === 'closure(BikeReturnEvent $event)') {
                $this->fail('TestEventListener was not called');
            }
        }
    }
}
