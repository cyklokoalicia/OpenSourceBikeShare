<?php

declare(strict_types=1);

namespace BikeShare\Test\Application\Controller;

use BikeShare\App\Security\UserProvider;
use BikeShare\Db\DbInterface;
use BikeShare\Event\BikeRentEvent;
use BikeShare\Event\BikeReturnEvent;
use BikeShare\Rent\RentSystemFactory;
use BikeShare\Repository\BikeRepository;
use BikeShare\Repository\StandRepository;
use BikeShare\Repository\UserRepository;
use BikeShare\Test\Application\BikeSharingWebTestCase;
use Symfony\Component\HttpFoundation\Request;

class ScanControllerTest extends BikeSharingWebTestCase
{
    private const USER_PHONE_NUMBER = '421951111111';
    private const ADMIN_PHONE_NUMBER = '421951222222';
    private const BIKE_NUMBER = 5;
    private const STAND_NAME = 'STAND5';

    private $watchesTooMany;

    protected function setUp(): void
    {
        parent::setUp();
        $this->watchesTooMany = $_ENV['WATCHES_NUMBER_TOO_MANY'];

        $admin = $this->client->getContainer()->get(UserRepository::class)
            ->findItemByPhoneNumber(self::ADMIN_PHONE_NUMBER);

        $this->client->getContainer()->get(RentSystemFactory::class)->getRentSystem('sms')
            ->returnBike(
                $admin['userId'],
                self::BIKE_NUMBER,
                self::STAND_NAME,
                '',
                true
            );
    }

    protected function tearDown(): void
    {
        $admin = $this->client->getContainer()->get(UserRepository::class)
            ->findItemByPhoneNumber(self::ADMIN_PHONE_NUMBER);
        $this->client->getContainer()->get(RentSystemFactory::class)->getRentSystem('sms')
            ->returnBike(
                $admin['userId'],
                self::BIKE_NUMBER,
                self::STAND_NAME,
                '',
                true
            );

        $_ENV['WATCHES_NUMBER_TOO_MANY'] = $this->watchesTooMany;
        parent::tearDown();
    }

    public function testRent(): void
    {
        $user = $this->client->getContainer()->get(UserProvider::class)->loadUserByIdentifier(self::USER_PHONE_NUMBER);
        $this->client->loginUser($user);

        $crawler = $this->client->request(
            Request::METHOD_GET,
            '/scan.php/rent/' . self::BIKE_NUMBER
        );
        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('h3', 'Rent bike ' . self::BIKE_NUMBER . ' on stand ' . self::STAND_NAME);
        $this->assertFormValue('form', 'rent', 'yes');

        $form = $crawler->selectButton('rentButton')->form();

        $this->client->getContainer()->get('event_dispatcher')->addListener(
            BikeRentEvent::class,
            function (BikeRentEvent $event) use ($user) {
                $this->assertSame(self::BIKE_NUMBER, $event->getBikeNumber(), 'Invalid bike number');
                $this->assertSame(false, $event->isForce());
                $this->assertSame($user->getUserId(), $event->getUserId());
            }
        );

        $crawler = $this->client->submit($form);
        $this->assertResponseIsSuccessful();
        $rentText = $crawler->filter('.alert-success')->text();

        $this->assertMatchesRegularExpression(
            '/Bike ' . self::BIKE_NUMBER . ': Open with code \d{4}\.Change code immediately to \d{4}' .
            '\(open, rotate metal part, set new code, rotate metal part back\)\./',
            $rentText,
            'Invalid message about success rent'
        );

        $bike = $this->client->getContainer()->get(BikeRepository::class)->findItem(self::BIKE_NUMBER);

        $this->assertEquals($user->getUserId(), $bike['userId'], 'Bike rented by another user');
        $this->assertNull($bike['standName'], 'Bike is still on stand');

        $history = $this->client->getContainer()->get(DbInterface::class)->query(
            'SELECT * FROM history WHERE userId = :userId AND bikeNum = :bikeNum ORDER BY id DESC LIMIT 1',
            [
                'userId' => $user->getUserId(),
                'bikeNum' => self::BIKE_NUMBER,
            ]
        )->fetchAssoc();

        $this->assertSame($history['action'], 'RENT', 'Invalid history action');
        $this->assertNotEmpty($history['parameter'], 'Missed lock code');
        $this->assertStringContainsString(
            'Change code immediately to ' . str_pad($history['parameter'], 4, '0', STR_PAD_LEFT),
            $rentText,
            'Response text does not contain lock code'
        );

        $notCalledListeners = $this->client->getContainer()->get('event_dispatcher')->getNotCalledListeners();
        foreach ($notCalledListeners as $listener) {
            if ($listener['pretty'] === 'BikeShare\EventListener\TooManyBikeRentEventListener::__invoke') {
                $this->fail('TooManyBikeRentEventListener was not called');
            }
        }

        $received = $this->client->getContainer()->get(DbInterface::class)->query(
            'SELECT * FROM received WHERE sender = :sender ORDER BY receive_time DESC, id DESC LIMIT 1',
            ['sender' => self::USER_PHONE_NUMBER]
        )->fetchAssoc();

        $this->assertSame(
            '/scan.php/rent/' . self::BIKE_NUMBER,
            $received['sms_text'],
            'Received message is not logged'
        );
        $this->assertEmpty($received['sms_uuid'], 'Web request should not have sms_uuid');

        $sent = $this->client->getContainer()->get(DbInterface::class)->query(
            'SELECT * FROM sent WHERE number = :number ORDER BY time DESC, id DESC LIMIT 1',
            ['number' => self::USER_PHONE_NUMBER]
        )->fetchAssoc();
        $this->assertMatchesRegularExpression(
            '/Bike ' . self::BIKE_NUMBER . ': Open with code \d{4}\.Change code immediately to \d{4}' .
            '\(open, rotate metal part, set new code, rotate metal part back\)\./',
            $sent['text'],
            'Send message is not logged'
        );
        $this->assertStringContainsString(
            'Change code immediately to ' . str_pad($history['parameter'], 4, '0', STR_PAD_LEFT),
            $sent['text'],
            'Log record does not contain lock code'
        );
    }

    public function testReturn(): void
    {
        $user = $this->client->getContainer()->get(UserProvider::class)->loadUserByIdentifier(self::USER_PHONE_NUMBER);
        $this->client->loginUser($user);

        $this->client->request(
            Request::METHOD_POST,
            '/scan.php/rent/' . self::BIKE_NUMBER,
            ['rent' => 'yes']
        );

        $this->client->getContainer()->get('event_dispatcher')->addListener(
            BikeReturnEvent::class,
            function (BikeReturnEvent $event) use ($user) {
                $this->assertSame(self::BIKE_NUMBER, $event->getBikeNumber(), 'Invalid bike number');
                $this->assertSame(false, $event->isForce());
                $this->assertSame($user->getUserId(), $event->getUserId());
            }
        );

        $crawler = $this->client->request(
            Request::METHOD_GET,
            '/scan.php/return/' . self::STAND_NAME
        );
        $this->assertResponseIsSuccessful();
        $returnText = $crawler->filter('.alert-success')->text();

        $this->assertMatchesRegularExpression(
            '/Bike ' . self::BIKE_NUMBER . ' returned to stand ' . self::STAND_NAME . ' : Lock with code \d{4}\.' .
            'Please, rotate the lockpad to 0000 when leaving\.Wipe the bike clean if it is dirty, please\./',
            $returnText,
            'Invalid return message'
        );
        $bike = $this->client->getContainer()->get(BikeRepository::class)->findItem(self::BIKE_NUMBER);
        $stand = $this->client->getContainer()->get(StandRepository::class)->findItemByName(self::STAND_NAME);

        $this->assertNull($bike['userId'], 'Bike is rented by user');
        $this->assertSame($stand['standName'], $bike['standName'], 'Bike is on invalid stand');

        $history = $this->client->getContainer()->get(DbInterface::class)->query(
            'SELECT * FROM history WHERE userId = :userId AND bikeNum = :bikeNum ORDER BY id DESC LIMIT 1',
            [
                'userId' => $user->getUserId(),
                'bikeNum' => self::BIKE_NUMBER,
            ]
        )->fetchAssoc();

        $this->assertSame('RETURN', $history['action'], 'Invalid history action');
        $this->assertEquals($stand['standId'], $history['parameter'], 'Missed standId');

        $notCalledListeners = $this->client->getContainer()->get('event_dispatcher')->getNotCalledListeners();
        foreach ($notCalledListeners as $listener) {
            if ($listener['stub'] === 'closure(BikeReturnEvent $event)') {
                $this->fail('TestEventListener was not called');
            }
        }

        $received = $this->client->getContainer()->get(DbInterface::class)->query(
            'SELECT * FROM received WHERE sender = :sender ORDER BY receive_time DESC, id DESC LIMIT 1',
            ['sender' => self::USER_PHONE_NUMBER]
        )->fetchAssoc();
        $this->assertSame(
            '/scan.php/return/' . self::STAND_NAME,
            $received['sms_text'],
            'Received message is not logged'
        );
        $this->assertEmpty($received['sms_uuid'], 'Web request should not have sms_uuid');

        $sent = $this->client->getContainer()->get(DbInterface::class)->query(
            'SELECT * FROM sent WHERE number = :number ORDER BY time DESC, id DESC LIMIT 1',
            ['number' => self::USER_PHONE_NUMBER]
        )->fetchAssoc();
        $this->assertMatchesRegularExpression(
            '/Bike ' . self::BIKE_NUMBER . ' returned to stand ' . self::STAND_NAME . ' : Lock with code \d{4}\.' .
                'Please, rotate the lockpad to 0000 when leaving\.Wipe the bike clean if it is dirty, please\./',
            $sent['text'],
            'Send message is not logged'
        );
    }
}
