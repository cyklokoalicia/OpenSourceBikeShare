<?php

declare(strict_types=1);

namespace BikeShare\Test\Integration\EventListener;

use BikeShare\Event\BikeRentEvent;
use BikeShare\Mail\MailSenderInterface;
use BikeShare\Repository\HistoryRepository;
use BikeShare\Repository\UserRepository;
use BikeShare\Test\Integration\BikeSharingKernelTestCase;
use Symfony\Component\Clock\Test\ClockSensitiveTrait;
use Symfony\Component\Translation\TranslatableMessage;

class TooManyBikeRentEventListenerTest extends BikeSharingKernelTestCase
{
    use ClockSensitiveTrait;

    private const USER_PHONE_NUMBER = '421951111111';
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
                new \DateTimeImmutable(self::CURRENT_TIME)
                    ->sub(new \DateInterval('PT' . $_ENV['WATCHES_NUMBER_TOO_MANY'] . 'H'))
            )->willReturn(self::RENT_COUNT);
        $this->getContainer()->set(HistoryRepository::class, $historyRepository);

        $eventDispatcher = $this->getContainer()->get('event_dispatcher');
        $eventDispatcher->dispatch(new BikeRentEvent(self::BIKE_NUMBER, $user['userId'], false));

        $mailSender = self::getContainer()->get(MailSenderInterface::class);
        $adminMails = array_column(self::getContainer()->get(UserRepository::class)->findAdmins(), 'mail');
        $this->assertEqualsCanonicalizing($adminMails, array_column($mailSender->getSentMessages(), 'recipient'));
        $sentMessage = $mailSender->getSentMessages()[0];
        $this->assertEquals(new TranslatableMessage('notification.subject.too_many_rents'), $sentMessage['subject']);
        $this->assertEquals(
            new TranslatableMessage('admin.notification.too_many_rents', [
                'hour' => $_ENV['WATCHES_NUMBER_TOO_MANY'],
                'userName' => $user['userName'],
                'phone' => self::USER_PHONE_NUMBER,
                'count' => self::RENT_COUNT,
            ]),
            $sentMessage['message'],
            'Invalid message for admin email'
        );
    }
}
