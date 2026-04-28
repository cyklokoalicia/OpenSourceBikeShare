<?php

declare(strict_types=1);

namespace BikeShare\Test\Application\Controller\SmsRequestController;

use BikeShare\Db\DbInterface;
use BikeShare\Event\BikeRentEvent;
use BikeShare\Repository\BikeRepository;
use BikeShare\Repository\UserRepository;
use BikeShare\Sms\DebugSmsSender;
use BikeShare\Test\Application\BikeSharingWebTestCase;
use BikeShare\Translation\TranslatableResult;
use Symfony\Component\HttpFoundation\Request;

class ForceRentCommandTest extends BikeSharingWebTestCase
{
    private const ADMIN_PHONE_NUMBER = '421951222222';
    private const BIKE_NUMBER = 3;

    public function testForceRentCommand(): void
    {
        $user = $this->client->getContainer()->get(UserRepository::class)
            ->findItemByPhoneNumber(self::ADMIN_PHONE_NUMBER);

        $this->client->getContainer()->get('event_dispatcher')->addListener(
            BikeRentEvent::class,
            function (BikeRentEvent $event) use ($user) {
                $this->assertSame(self::BIKE_NUMBER, $event->getBikeNumber(), 'Invalid bike number');
                $this->assertSame(true, $event->isForce());
                $this->assertSame($user['userId'], $event->getUserId());
            }
        );

        $this->client->request(
            Request::METHOD_GET,
            '/receive.php',
            [
                'number' => self::ADMIN_PHONE_NUMBER,
                'message' => 'FORCERENT ' . self::BIKE_NUMBER,
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
        $this->assertInstanceOf(TranslatableResult::class, $sentMessage['message']);
        $this->assertSame('bike.rent.success', $sentMessage['message']->getCode());
        $params = $sentMessage['message']->getParams();
        $this->assertSame(self::BIKE_NUMBER, $params['bikeNumber']);
        $this->assertMatchesRegularExpression('/^\d{4}$/', (string)$params['currentCode']);
        $this->assertMatchesRegularExpression('/^\d{4}$/', (string)$params['newCode']);
        $this->assertNotSame($params['currentCode'], $params['newCode']);

        $bike = $this->client->getContainer()->get(BikeRepository::class)->findItem(self::BIKE_NUMBER);

        $this->assertEquals($user['userId'], $bike['userId'], 'Bike rented by another user');
        $this->assertNull($bike['standName'], 'Bike is still on stand');

        $history = $this->client->getContainer()->get(DbInterface::class)->query(
            'SELECT * FROM history WHERE userId = :userId AND bikeNum = :bikeNum ORDER BY id DESC LIMIT 1',
            [
                'userId' => $user['userId'],
                'bikeNum' => self::BIKE_NUMBER,
            ]
        )->fetchAssoc();

        $this->assertSame($history['action'], 'FORCERENT', 'Invalid history action');
        $this->assertNotEmpty($history['parameter'], 'Missed lock code');
        $this->assertSame(
            str_pad((string)$history['parameter'], 4, '0', STR_PAD_LEFT),
            (string)$params['newCode'],
            'Response sms newCode does not match history'
        );

        $notCalledListeners = $this->client->getContainer()->get('event_dispatcher')->getNotCalledListeners();
        foreach ($notCalledListeners as $listener) {
            if ($listener['pretty'] === 'BikeShare\EventListener\TooManyBikeRentEventListener::__invoke') {
                $this->fail('TooManyBikeRentEventListener was not called');
            }

            if ($listener['stub'] === 'closure(BikeRentEvent $event)') {
                $this->fail('TestEventListener was not called');
            }
        }
    }
}
