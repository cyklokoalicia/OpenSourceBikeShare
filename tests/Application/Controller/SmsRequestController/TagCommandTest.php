<?php

declare(strict_types=1);

namespace BikeShare\Test\Application\Controller\SmsRequestController;

use BikeShare\Db\DbInterface;
use BikeShare\Mail\MailSenderInterface;
use BikeShare\Repository\NoteRepository;
use BikeShare\Repository\StandRepository;
use BikeShare\Repository\UserRepository;
use BikeShare\Sms\DebugSmsSender;
use BikeShare\Test\Application\BikeSharingWebTestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Translation\TranslatableMessage;

class TagCommandTest extends BikeSharingWebTestCase
{
    private const USER_PHONE_NUMBER = '421951111111';
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

    public function testTagCommand(): void
    {
        $standName = self::STAND_NAME;
        $note = 'Test note for bikes on stand';
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
                'message' => 'TAG ' . $standName . ' ' . $note,
                'uuid' => md5((string)microtime(true)),
                'time' => time(),
            ]
        );
        $this->assertResponseIsSuccessful();
        $this->assertSame('', $this->client->getResponse()->getContent());

        #One message is sent to admin, one to user
        $smsSender = $this->client->getContainer()->get(DebugSmsSender::class);
        $this->assertCount(2, $smsSender->getSentMessages());
        $sentMessages = $smsSender->getSentMessages();

        $notifiedNumbers = [];
        foreach ($sentMessages as $sentMessage) {
            $this->assertInstanceOf(TranslatableMessage::class, $sentMessage['message']);
            if ($sentMessage['number'] === self::USER_PHONE_NUMBER) {
                $this->assertSame('command.tag.success', $sentMessage['message']->getMessage());
                $this->assertSame(
                    ['standName' => $standName, 'note' => $note],
                    $sentMessage['message']->getParameters(),
                    'Invalid message sent to user'
                );
            } else {
                $this->assertSame('admin.notification.sms_processed', $sentMessage['message']->getMessage());
                $params = $sentMessage['message']->getParameters();
                $this->assertSame($user['userName'], $params['userName']);
                $this->assertInstanceOf(TranslatableMessage::class, $params['message']);
                $this->assertSame('command.tag.success', $params['message']->getMessage());
                $this->assertSame(
                    ['standName' => $standName, 'note' => $note],
                    $params['message']->getParameters()
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
            $this->assertSame(
                $user['userName'] . ': All bikes on stand ' . $standName . ' tagged with note "' . $note . '".',
                $sentMessage['message']
            );
            $this->assertSame('OpenSourceBikeShare notification', $sentMessage['subject']);
            $this->assertContains($sentMessage['recipient'], array_column($admins, 'mail'));
        }

        $bikes = $this->client->getContainer()->get(StandRepository::class)->findBikesOnStand(self::STAND_ID);
        foreach ($bikes as $bike) {
            $bikeNotes = $this->client->getContainer()->get(NoteRepository::class)->findBikeNote($bike['bikeNum']);
            $this->assertCount(1, $bikeNotes);
            $this->assertSame($note, $bikeNotes[0]['note']);
        }
    }

    private function cleanupNotes(): void
    {
        $this->client->getContainer()->get(NoteRepository::class)
            ->deleteNotesForAllBikesOnStand(self::STAND_ID, null);
    }
}
