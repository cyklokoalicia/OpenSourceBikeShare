<?php

declare(strict_types=1);

namespace BikeShare\Test\Integration\Rent;

use BikeShare\Credit\CreditSystemInterface;
use BikeShare\Db\DbInterface;
use BikeShare\Event\BikeRentEvent;
use BikeShare\Rent\RentSystemFactory;
use BikeShare\Repository\BikeRepository;
use BikeShare\Repository\StandRepository;
use BikeShare\Repository\UserRepository;
use BikeShare\Test\Integration\BikeSharingKernelTestCase;
use Symfony\Component\Clock\Test\ClockSensitiveTrait;

class RentSystemTest extends BikeSharingKernelTestCase
{
    use ClockSensitiveTrait;

    private const USER_PHONE_NUMBER = '421951111111';
    private const ADMIN_PHONE_NUMBER = '421951222222';
    private const BIKE_NUMBER = 5;
    private const STAND_NAME = 'STAND5';

    private array $configuration = [];

    protected function setUp(): void
    {
        $this->configuration = $_ENV;
        parent::setUp();
        #force return bike by admin
        $admin = self::getContainer()->get(UserRepository::class)
            ->findItemByPhoneNumber(self::ADMIN_PHONE_NUMBER);
        self::getContainer()->get(RentSystemFactory::class)->getRentSystem('web')
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
        #remove user credit if any
        $user = self::getContainer()->get(UserRepository::class)
            ->findItemByPhoneNumber(self::USER_PHONE_NUMBER);
        $creditSystem = self::getContainer()->get(CreditSystemInterface::class);
        $userCredit = $creditSystem->getUserCredit($user['userId']);
        if ($userCredit > 0) {
            $creditSystem->useCredit($user['userId'], $userCredit);
        }
        #force return bike by admin
        $admin = self::getContainer()->get(UserRepository::class)
            ->findItemByPhoneNumber(self::ADMIN_PHONE_NUMBER);
        self::getContainer()->get(RentSystemFactory::class)->getRentSystem('web')
            ->returnBike(
                $admin['userId'],
                self::BIKE_NUMBER,
                self::STAND_NAME,
                '',
                true
            );

        $_ENV = $this->configuration;
        parent::tearDown();
    }

    /**
     * @dataProvider rentCommandDataProvider
     */
    public function testReturnCommand(
        array $configuration,
        float $userCredit,
        float $expectedCreditLeft,
        string $expectedCreditHistory,
        int $returnTimeMoveToFuture
    ): void {
        self::bootKernel();
        foreach ($configuration as $key => $value) {
            $_ENV[$key] = $value;
        }
        $db = self::getContainer()->get(DbInterface::class);
        $user = self::getContainer()->get(UserRepository::class)->findItemByPhoneNumber(self::USER_PHONE_NUMBER);
        $db->query('DELETE FROM history WHERE userId = :userId', ['userId' => $user['userId']]);

        static::mockTime();

        self::getContainer()->get(CreditSystemInterface::class)->addCredit($user['userId'], $userCredit);

        self::getContainer()->get('event_dispatcher')->addListener(
            BikeRentEvent::class,
            function (BikeRentEvent $event) use ($user) {
                $this->assertSame(self::BIKE_NUMBER, $event->getBikeNumber(), 'Invalid bike number');
                $this->assertSame(false, $event->isForce());
                $this->assertSame($user['userId'], $event->getUserId());
            }
        );

        #rent bike by user
        self::getContainer()->get(RentSystemFactory::class)->getRentSystem('web')
            ->rentBike($user['userId'], self::BIKE_NUMBER);

        static::mockTime('+' . $returnTimeMoveToFuture . ' seconds');
        #return bike by user
        $result = self::getContainer()->get(RentSystemFactory::class)->getRentSystem('web')
            ->returnBike($user['userId'], self::BIKE_NUMBER, self::STAND_NAME);

        $bike = self::getContainer()->get(BikeRepository::class)->findItem(self::BIKE_NUMBER);
        $stand = self::getContainer()->get(StandRepository::class)->findItemByName(self::STAND_NAME);

        $this->assertNull($bike['userId'], 'Bike is rented by user');
        $this->assertSame($stand['standName'], $bike['standName'], 'Bike is on invalid stand');

        $history = self::getContainer()->get(DbInterface::class)->query(
            'SELECT * FROM history WHERE userId = :userId AND bikeNum = :bikeNum ORDER BY id DESC LIMIT 1',
            [
                'userId' => $user['userId'],
                'bikeNum' => self::BIKE_NUMBER,
            ]
        )->fetchAssoc();

        $this->assertSame('RETURN', $history['action'], 'Invalid history action');
        $this->assertEquals($stand['standId'], $history['parameter'], 'Missed standId');

        $notCalledListeners = self::getContainer()->get('event_dispatcher')->getNotCalledListeners();
        foreach ($notCalledListeners as $listener) {
            if ($listener['stub'] === 'closure(BikeReturnEvent $event)') {
                $this->fail('TestEventListener was not called');
            }
        }
        $creditSystem = self::getContainer()->get(CreditSystemInterface::class);
        $this->assertSame($expectedCreditLeft, $creditSystem->getUserCredit($user['userId']), 'Invalid credit left');

        $history = $db->query(
            'SELECT * FROM history WHERE userId = :userId ORDER BY time DESC, id DESC LIMIT 3',
            ['userId' => $user['userId']]
        )->fetchAllAssoc();

        $this->assertCount(3, $history, 'Invalid history count');
        foreach ($history as $item) {
            if ($item['action'] === 'CREDIT') {
                $this->assertSame((string)$expectedCreditLeft, $item['parameter'], 'Invalid credit amount in history');
            } elseif ($item['action'] === 'CREDITCHANGE') {
                $this->assertSame($expectedCreditHistory, $item['parameter'], 'Invalid info about rent fee in history');
            }
        }
    }

    public function rentCommandDataProvider(): iterable
    {
        $startCredit = 100;

        $default = [
            'configuration' => [
                'CREDIT_SYSTEM_ENABLED' => true,
                'CREDIT_SYSTEM_PRICE_CYCLE' => 0, // 0 means disabled
                'CREDIT_SYSTEM_RENTAL_FEE' => 10, // 10 credits
                'CREDIT_SYSTEM_LONG_RENTAL_FEE' => 20, // 20 credits for the long rental
                'WATCHES_FREE_TIME' => 5, // 5 minutes of free time
                'WATCHES_LONG_RENTAL' => 2, // 2 hours long rental
                'WATCHES_FLAT_PRICE_CYCLE' => '0',
                'WATCHES_DOUBLE_PRICE_CYCLE' => '0',
                'WATCHES_DOUBLE_PRICE_CYCLE_CAP' => '0',
                'WATCHES_NUMBER_TOO_MANY' => 999, // no notify for too many bikes
            ],
            'userCredit' => $startCredit,
            'expectedCreditLeft' => $startCredit,
            'expectedCreditHistory' => '0|',
            'returnTimeMoveToFuture' => 60, // 60 seconds, which is less than free time
        ];

        yield 'return without credit charge' => $default;

        yield 'credit charge - end of free time' => array_replace_recursive(
            $default,
            [
                'configuration' => [
                    'CREDIT_SYSTEM_RENTAL_FEE' => 10, // 10 credits
                    'WATCHES_FREE_TIME' => 10, // 10 minutes of free time
                ],
                'expectedCreditLeft' => $startCredit - 10,
                'expectedCreditHistory' => '10|overfree-10;',
                'returnTimeMoveToFuture' => 10 * 60 + 1// more than free time, so credit will be charged
            ]
        );

        yield 'credit charge - long rental' => array_replace_recursive(
            $default,
            [
                'configuration' => [
                    'CREDIT_SYSTEM_RENTAL_FEE' => 10, // 10 credits
                    'CREDIT_SYSTEM_LONG_RENTAL_FEE' => 20, // 20 credits
                    'WATCHES_LONG_RENTAL' => 1, // 1 hour for long rental
                    'WATCHES_FREE_TIME' => 10, // 10 minute of free time
                ],
                'expectedCreditLeft' => $startCredit - 10 - 20,
                'expectedCreditHistory' => '30|overfree-10;longrent-20;',
                'returnTimeMoveToFuture' => (10 + 60) * 60 // more than the long rental time, so credit will be charged
            ]
        );

        yield 'credit charge - flat price cycle' => array_replace_recursive(
            $default,
            [
                'configuration' => [
                    'CREDIT_SYSTEM_PRICE_CYCLE' => 1, // charge the flat price every WATCHES_FLAT_PRICE_CYCLE minutes
                    'CREDIT_SYSTEM_RENTAL_FEE' => 10, // 10 credits
                    'WATCHES_FREE_TIME' => 0, // 0 minutes of free time
                    'WATCHES_FLAT_PRICE_CYCLE' => 5, // charge the flat price every 5 minutes
                ],
                'expectedCreditLeft' => $startCredit - 10 - 10 * 2, // rental fee + two times of flat price charge
                'expectedCreditHistory' => '30|overfree-10;flat-20;',
                'returnTimeMoveToFuture' => 10 * 60 + 1 // two times flat price cycle (5 minutes) + 1 second
            ]
        );

        yield 'credit charge - double price cycle' => array_replace_recursive(
            $default,
            [
                'configuration' => [
                    'CREDIT_SYSTEM_PRICE_CYCLE' => 2, // charge double price every WATCHES_DOUBLE_PRICE_CYCLE minutes
                    'CREDIT_SYSTEM_RENTAL_FEE' => 2, // 2 credits
                    'WATCHES_FREE_TIME' => 0, // 0 minutes of free time
                    'WATCHES_DOUBLE_PRICE_CYCLE' => 5, // charge double price every 5 minutes
                    'WATCHES_DOUBLE_PRICE_CYCLE_CAP' => 3, // charge double price only 3 times
                ],
                'expectedCreditLeft' => $startCredit - 2 - 2 - 2 * 2 - 2 * 4 - 2 * 4,
                // rental fee (2) + first 5 minutes (2) + second 5 minutes (2 * 2)
                // + third 5 minutes (2 * 4) + fourth 5 minutes (2 * 4)
                'expectedCreditHistory' => '24|overfree-2;double-2;double-4;double-8;double-8;',
                'returnTimeMoveToFuture' => 20 * 60 + 1 // 20 minutes (4 * 5 minutes) + 1 second
            ]
        );
    }

    // if the bike is returned and rented again within 10 minutes, a user will not have new free time.
    public function testDoubleRent(): void
    {
        $_ENV['CREDIT_SYSTEM_ENABLED'] = true;
        $_ENV['WATCHES_FREE_TIME'] = 30; // 30 minutes of free time
        $_ENV['CREDIT_SYSTEM_RENTAL_FEE'] = 10; // 10 credits for rental

        self::bootKernel();
        static::mockTime();

        $user = self::getContainer()->get(UserRepository::class)->findItemByPhoneNumber(self::USER_PHONE_NUMBER);
        $creditSystem = self::getContainer()->get(CreditSystemInterface::class);
        $rentSystem = self::getContainer()->get(RentSystemFactory::class)->getRentSystem('web');
        $db = self::getContainer()->get(DbInterface::class);

        $db->query('DELETE FROM history WHERE userId = :userId', ['userId' => $user['userId']]);
        $creditSystem->addCredit($user['userId'], 100);
        $rentSystem->rentBike($user['userId'], self::BIKE_NUMBER);
        static::mockTime('+ 25 minutes');
        $rentSystem->returnBike($user['userId'], self::BIKE_NUMBER, self::STAND_NAME);
        static::mockTime('+ 5 minutes');
        $rentSystem->rentBike($user['userId'], self::BIKE_NUMBER);
        static::mockTime('+ 25 minutes');
        $rentSystem->returnBike($user['userId'], self::BIKE_NUMBER, self::STAND_NAME);

        $this->assertSame(90.0, $creditSystem->getUserCredit($user['userId']));

        $history = $db->query(
            'SELECT * FROM history WHERE userId = :userId ORDER BY time DESC, id DESC LIMIT 3',
            ['userId' => $user['userId']]
        )->fetchAllAssoc();

        $this->assertCount(3, $history, 'Invalid history count');
        foreach ($history as $item) {
            if ($item['action'] === 'CREDIT') {
                $this->assertSame('90', $item['parameter'], 'Invalid credit amount in history');
            } elseif ($item['action'] === 'CREDITCHANGE') {
                $this->assertSame('10|rerent-10;', $item['parameter'], 'Invalid info about rent fee in history');
            }
        }
    }
}
