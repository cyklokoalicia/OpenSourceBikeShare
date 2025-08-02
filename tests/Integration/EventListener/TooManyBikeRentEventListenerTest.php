<?php

declare(strict_types=1);

namespace BikeShare\Test\Integration\EventListener;

use BikeShare\Event\BikeRentEvent;
use BikeShare\Mail\MailSenderInterface;
use BikeShare\Repository\HistoryRepository;
use BikeShare\Repository\UserRepository;
use BikeShare\Test\Integration\BikeSharingKernelTestCase;
use Symfony\Component\Clock\Test\ClockSensitiveTrait;

class TooManyBikeRentEventListenerTest extends BikeSharingKernelTestCase
{
    use ClockSensitiveTrait;

    private const USER_PHONE_NUMBER = '421111111111';
    private const BIKE_NUMBER = 1;
    private const CURRENT_TIME = '2023-10-01 12:00:00';
    private const RENT_COUNT = 999; //set too big for the test

    public function testSomething(): void
    {
        static::mockTime(self::CURRENT_TIME);

        $user = self::getContainer()->get(UserRepository::class)
            ->findItemByPhoneNumber(self::USER_PHONE_NUMBER);

        $historyRepository = $this->createMock(HistoryRepository::class);
        $historyRepository
            ->expects($this->once())
            ->method('findRentCountByUser')
            ->with(
                $user['userId'],
                (new \DateTimeImmutable(self::CURRENT_TIME))
                    ->sub(new \DateInterval('PT' . $_ENV['WATCHES_NUMBER_TOO_MANY'] . 'H'))
            )->willReturn(self::RENT_COUNT);
        $this->getContainer()->set(HistoryRepository::class, $historyRepository);

        $eventDispatcher = $this->getContainer()->get('event_dispatcher');
        $eventDispatcher->dispatch(new BikeRentEvent(self::BIKE_NUMBER, $user['userId'], false));

        $mailSender = self::getContainer()->get(MailSenderInterface::class);
        $this->assertCount(1, $mailSender->getSentMessages(), 'Invalid number of sent email');
        $sentMessage = $mailSender->getSentMessages()[0];
        $this->assertStringContainsString(
            'Bike rental over limit in ' . $_ENV['WATCHES_NUMBER_TOO_MANY'] . ' hour',
            $sentMessage['message'],
            'Invalid message for admin email'
        );
        $this->assertStringContainsString(
            self::USER_PHONE_NUMBER,
            $sentMessage['message'],
            'Invalid message for admin email'
        );
    }
}
