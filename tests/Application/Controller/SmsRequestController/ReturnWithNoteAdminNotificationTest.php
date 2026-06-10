<?php

declare(strict_types=1);

namespace BikeShare\Test\Application\Controller\SmsRequestController;

use BikeShare\Rent\Enum\RentSystemType;
use BikeShare\Rent\RentSystemFactory;
use BikeShare\Repository\UserRepository;
use BikeShare\Sms\DebugSmsSender;
use BikeShare\Test\Application\BikeSharingWebTestCase;
use Symfony\Component\Translation\TranslatableMessage;

class ReturnWithNoteAdminNotificationTest extends BikeSharingWebTestCase
{
    private const USER_PHONE_NUMBER = '421951111111';
    private const ADMIN_PHONE_NUMBER = '421951222222';
    private const BIKE_NUMBER = 6;
    private const STAND_NAME = 'STAND1';
    private const NOTE = 'broken brake';

    protected function setUp(): void
    {
        $this->setEnvVar('WATCHES_NUMBER_TOO_MANY', '9999'); // Disable too-many-rentals notification
        parent::setUp();

        $admin = $this->client->getContainer()->get(UserRepository::class)
            ->findItemByPhoneNumber(self::ADMIN_PHONE_NUMBER);

        #force return bike by admin so it is parked and free
        $this->client->getContainer()->get(RentSystemFactory::class)->getRentSystem(RentSystemType::SMS)
            ->returnBike($admin['userId'], self::BIKE_NUMBER, self::STAND_NAME, '', true);

        #rent bike by user
        $user = $this->client->getContainer()->get(UserRepository::class)
            ->findItemByPhoneNumber(self::USER_PHONE_NUMBER);
        $this->client->getContainer()->get(RentSystemFactory::class)->getRentSystem(RentSystemType::SMS)
            ->rentBike($user['userId'], self::BIKE_NUMBER);

        // Drop setup-noise so the test sees only what the note-bearing RETURN produces.
        $this->client->getContainer()->get(DebugSmsSender::class)->reset();
    }

    public function testReturnWithNoteNotifiesAdmin(): void
    {
        $user = $this->client->getContainer()->get(UserRepository::class)
            ->findItemByPhoneNumber(self::USER_PHONE_NUMBER);

        $this->client->getContainer()->get(RentSystemFactory::class)->getRentSystem(RentSystemType::SMS)
            ->returnBike($user['userId'], self::BIKE_NUMBER, self::STAND_NAME, self::NOTE);

        $sent = $this->client->getContainer()->get(DebugSmsSender::class)->getSentMessages();

        $adminMessages = $this->noteNotifications($sent);

        $this->assertCount(
            1,
            $adminMessages,
            'Admin should receive exactly one bike.note.admin.notification on return-with-note'
        );

        $params = $adminMessages[0]['message']->getParameters();
        $this->assertSame(self::BIKE_NUMBER, $params['bikeNumber']);
        $this->assertSame(self::NOTE, $params['userNote']);
        $this->assertSame($user['userName'], $params['userName']);
    }

    public function testReturnWithoutNoteDoesNotNotifyAdmin(): void
    {
        $user = $this->client->getContainer()->get(UserRepository::class)
            ->findItemByPhoneNumber(self::USER_PHONE_NUMBER);

        $this->client->getContainer()->get(RentSystemFactory::class)->getRentSystem(RentSystemType::SMS)
            ->returnBike($user['userId'], self::BIKE_NUMBER, self::STAND_NAME);

        $sent = $this->client->getContainer()->get(DebugSmsSender::class)->getSentMessages();

        $this->assertCount(
            0,
            $this->noteNotifications($sent),
            'No note notification expected when no note is provided'
        );
    }

    /**
     * @param array<int, array{number: string, message: mixed, locale: ?string}> $sent
     * @return list<array{number: string, message: mixed, locale: ?string}>
     */
    private function noteNotifications(array $sent): array
    {
        return array_values(array_filter(
            $sent,
            static fn (array $m): bool => $m['message'] instanceof TranslatableMessage
                && $m['message']->getMessage() === 'bike.note.admin.notification'
        ));
    }
}
