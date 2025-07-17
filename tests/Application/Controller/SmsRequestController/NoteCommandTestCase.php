<?php

declare(strict_types=1);

namespace BikeShare\Test\Application\Controller\SmsRequestController;

use BikeShare\Db\DbInterface;
use BikeShare\Mail\MailSenderInterface;
use BikeShare\Repository\NoteRepository;
use BikeShare\Repository\UserRepository;
use BikeShare\SmsConnector\SmsConnectorInterface;
use BikeShare\Test\Application\BikeSharingWebTestCase;
use Symfony\Component\HttpFoundation\Request;

class NoteCommandTestCase extends BikeSharingWebTestCase
{
    private const USER_PHONE_NUMBER = '421111111111';
    private const ADMIN_PHONE_NUMBER = '421222222222';
    private const BIKE_NUMBER = 1;
    private const STAND_NAME = 'STAND1';
    private const STAND_ID = 1;

    protected function setUp(): void
    {
        parent::setUp();
        $this->cleanupNotes();
    }

    protected function tearDown(): void
    {
        $this->cleanupNotes();
        parent::tearDown();
    }

    public function testNoteCommandForBike(): void
    {
        $bikeNumber = self::BIKE_NUMBER;
        $note = 'Test note for bike';
        $user = $this->client->getContainer()->get(UserRepository::class)
            ->findItemByPhoneNumber(self::USER_PHONE_NUMBER);

        $admins = $this->client->getContainer()->get(DbInterface::class)
            ->query('SELECT userId, number,mail FROM users where privileges & 2 != 0')
            ->fetchAllAssoc();

        $this->client->request(
            Request::METHOD_GET,
            '/receive.php',
            [
                'number' => self::USER_PHONE_NUMBER,
                'message' => 'NOTE ' . $bikeNumber . ' ' . $note,
                'uuid' => md5((string)microtime(true)),
                'time' => time(),
            ]
        );
        $this->assertResponseIsSuccessful();
        $this->assertSame('', $this->client->getResponse()->getContent());

        #One message is sent to admin, one to user
        $smsConnector = $this->client->getContainer()->get(SmsConnectorInterface::class);
        $this->assertCount(2, $smsConnector->getSentMessages());
        $sentMessages = $smsConnector->getSentMessages();

        $notifiedNumbers = [];
        foreach ($sentMessages as $sentMessage) {
            if ($sentMessage['number'] === self::USER_PHONE_NUMBER) {
                $this->assertSame(
                    'Note "' . $note . '" for bike ' . $bikeNumber . ' saved.',
                    $sentMessage['text'],
                    'Invalid message sent to user'
                );
            } else {
                $this->assertSame(
                    $user['username'] . ': Note "' . $note . '" for bike ' . $bikeNumber . ' saved.',
                    $sentMessage['text'],
                    'Invalid message sent to admin'
                );
            }
            $notifiedNumbers[] = $sentMessage['number'];
        }
        $this->assertEqualsCanonicalizing(
            array_merge([self::USER_PHONE_NUMBER], array_column($admins, 'number')),
            $notifiedNumbers,
            'Invalid notified numbers'
        );

        $mailSender = $this->client->getContainer()->get(MailSenderInterface::class);
        $this->assertCount(count($admins), $mailSender->getSentMessages(), 'No admin email was send');
        foreach ($mailSender->getSentMessages() as $sentMessage) {
            $this->assertSame($user['username'] . ': Note "' . $note . '" for bike ' . $bikeNumber . ' saved.', $sentMessage['message']);
            $this->assertSame('OpenSourceBikeShare notification', $sentMessage['subject']);
            $this->assertContains($sentMessage['recipient'], array_column($admins, 'mail'));
        }

        $bikeNotes = $this->client->getContainer()->get(NoteRepository::class)->findBikeNote($bikeNumber);
        $this->assertCount(1, $bikeNotes);
        $this->assertSame($note, $bikeNotes[0]['note']);
    }

    public function testNoteCommandForStand(): void
    {
        $standName = self::STAND_NAME;
        $note = 'Test note for stand';
        $user = $this->client->getContainer()->get(UserRepository::class)
            ->findItemByPhoneNumber(self::USER_PHONE_NUMBER);

        $admins = $this->client->getContainer()->get(DbInterface::class)
            ->query('SELECT userId, number,mail FROM users where privileges & 2 != 0')
            ->fetchAllAssoc();

        $this->client->request(
            Request::METHOD_GET,
            '/receive.php',
            [
                'number' => self::USER_PHONE_NUMBER,
                'message' => 'NOTE ' . $standName . ' ' . $note,
                'uuid' => md5((string)microtime(true)),
                'time' => time(),
            ]
        );
        $this->assertResponseIsSuccessful();
        $this->assertSame('', $this->client->getResponse()->getContent());

        #One message is sent to admin, one to user
        $smsConnector = $this->client->getContainer()->get(SmsConnectorInterface::class);
        $this->assertCount(2, $smsConnector->getSentMessages());
        $sentMessages = $smsConnector->getSentMessages();

        $notifiedNumbers = [];
        foreach ($sentMessages as $sentMessage) {
            if ($sentMessage['number'] === self::USER_PHONE_NUMBER) {
                $this->assertSame(
                    'Note "' . $note . '" for stand ' . $standName . ' saved.',
                    $sentMessage['text'],
                    'Invalid message sent to user'
                );
            } else {
                $this->assertSame(
                    $user['username'] . ': Note "' . $note . '" for stand ' . $standName . ' saved.',
                    $sentMessage['text'],
                    'Invalid message sent to admin'
                );
            }
            $notifiedNumbers[] = $sentMessage['number'];
        }
        $this->assertEqualsCanonicalizing(
            array_merge([self::USER_PHONE_NUMBER], array_column($admins, 'number')),
            $notifiedNumbers,
            'Invalid notified numbers'
        );

        $mailSender = $this->client->getContainer()->get(MailSenderInterface::class);
        $this->assertCount(count($admins), $mailSender->getSentMessages(), 'No admin email was send');
        foreach ($mailSender->getSentMessages() as $sentMessage) {
            $this->assertSame($user['username'] . ': Note "' . $note . '" for stand ' . $standName . ' saved.', $sentMessage['message']);
            $this->assertSame('OpenSourceBikeShare notification', $sentMessage['subject']);
            $this->assertContains($sentMessage['recipient'], array_column($admins, 'mail'));
        }

        $bikeNotes = $this->client->getContainer()->get(NoteRepository::class)->findStandNote(self::STAND_ID);
        $this->assertCount(1, $bikeNotes);
        $this->assertSame($note, $bikeNotes[0]['note']);
    }

    private function cleanupNotes(): void
    {
        $this->client->getContainer()->get(NoteRepository::class)->deleteBikeNote(self::BIKE_NUMBER, null);
        $this->client->getContainer()->get(NoteRepository::class)->deleteStandNote(self::STAND_ID, null);
    }
}
