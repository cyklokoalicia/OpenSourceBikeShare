<?php

declare(strict_types=1);

namespace BikeShare\Test\Application\Controller\SmsRequestController;

use BikeShare\Test\Support\MailAssert;
use PHPUnit\Framework\Attributes\DataProvider;
use BikeShare\Db\DbInterface;
use BikeShare\Mail\MailSenderInterface;
use BikeShare\Repository\NoteRepository;
use BikeShare\Repository\StandRepository;
use BikeShare\Repository\UserRepository;
use BikeShare\Sms\DebugSmsSender;
use BikeShare\Test\Application\BikeSharingWebTestCase;
use Monolog\Logger;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Translation\TranslatableMessage;

class UnTagCommandTest extends BikeSharingWebTestCase
{
    private const USER_PHONE_NUMBER = '421951111111';
    private const ADMIN_PHONE_NUMBER = '421951222222';
    private const STAND_NAME = 'STAND2';
    private const STAND_ID = 2;

    protected function setUp(): void
    {
        parent::setUp();

        $user = $this->client->getContainer()->get(UserRepository::class)
            ->findItemByPhoneNumber(self::USER_PHONE_NUMBER);

        $noteRepository = $this->client->getContainer()->get(NoteRepository::class);
        $noteRepository->deleteNotesForAllBikesOnStand(self::STAND_ID, null);

        $noteRepository->addNoteToAllBikesOnStand(self::STAND_ID, $user['userId'], 'Test note for bike');
        $noteRepository->addNoteToAllBikesOnStand(self::STAND_ID, $user['userId'], 'Note for bike');
    }

    #[DataProvider('unTagPatternDataProvider')]
    public function testUnTagCommandForStand(
        ?string $pattern,
        string $expectedCode,
        array $expectedParams,
        bool $notifiesAdmins,
        int $expectedRemainingNotes,
        bool $expectedWarning
    ): void {
        $standName = self::STAND_NAME;
        $user = $this->client->getContainer()->get(UserRepository::class)
            ->findItemByPhoneNumber(self::ADMIN_PHONE_NUMBER);

        $admins = $this->client->getContainer()->get(DbInterface::class)
            ->query('SELECT userId, number,mail FROM users where privileges & 2 != 0')
            ->fetchAllAssoc();

        $message = 'UNTAG ' . $standName;
        if ($pattern !== null) {
            $message .= ' ' . $pattern;
        }

        $this->client->request(
            Request::METHOD_GET,
            '/receive.php',
            [
                'number' => self::ADMIN_PHONE_NUMBER,
                'message' => $message,
                'uuid' => md5((string)microtime(true)),
                'time' => time(),
            ]
        );
        $this->assertResponseIsSuccessful();
        $this->assertSame('', $this->client->getResponse()->getContent());

        $smsSender = $this->client->getContainer()->get(DebugSmsSender::class);
        $this->assertCount($notifiesAdmins ? 1 + count($admins) : 1, $smsSender->getSentMessages());
        $sentMessages = $smsSender->getSentMessages();

        if (!$notifiesAdmins) {
            $sentMessage = $sentMessages[0];
            $this->assertInstanceOf(TranslatableMessage::class, $sentMessage['message']);
            $this->assertSame($expectedCode, $sentMessage['message']->getMessage(), 'Invalid message code');
            $this->assertSame($expectedParams, $sentMessage['message']->getParameters(), 'Invalid message params');
            $this->assertSame(self::ADMIN_PHONE_NUMBER, $sentMessage['number'], 'Invalid number');
        } else {
            $notifiedNumbers = [];
            foreach ($sentMessages as $sentMessage) {
                $this->assertInstanceOf(TranslatableMessage::class, $sentMessage['message']);
                if ($sentMessage['number'] === self::ADMIN_PHONE_NUMBER) {
                    $this->assertSame(
                        $expectedCode,
                        $sentMessage['message']->getMessage(),
                        'Invalid message sent to user'
                    );
                    $this->assertSame(
                        $expectedParams,
                        $sentMessage['message']->getParameters()
                    );
                } else {
                    $this->assertSame(
                        'admin.notification.sms_processed',
                        $sentMessage['message']->getMessage(),
                        'Invalid admin notification code'
                    );
                    $params = $sentMessage['message']->getParameters();
                    $this->assertSame($user['userName'], $params['userName']);
                    $this->assertInstanceOf(TranslatableMessage::class, $params['message']);
                    $this->assertSame($expectedCode, $params['message']->getMessage());
                    $this->assertSame($expectedParams, $params['message']->getParameters());
                }

                $notifiedNumbers[] = $sentMessage['number'];
            }

            $this->assertEqualsCanonicalizing(
                array_merge([self::ADMIN_PHONE_NUMBER], array_column($admins, 'number')),
                $notifiedNumbers,
                'Invalid notified numbers'
            );
        }

        $mailSender = $this->client->getContainer()->get(MailSenderInterface::class);
        $this->assertCount(
            $notifiesAdmins ? count($admins) : 0,
            $mailSender->getSentMessages(),
            $notifiesAdmins ? 'No admin email was send' : 'Unexpected admin email was send'
        );

        if ($notifiesAdmins) {
            foreach ($mailSender->getSentMessages() as $sentMessage) {
                MailAssert::smsAudit(
                    $sentMessage,
                    $user['userName'],
                    new TranslatableMessage($expectedCode, $expectedParams)
                );
                $this->assertContains($sentMessage['recipient'], array_column($admins, 'mail'));
            }
        }

        $bikes = $this->client->getContainer()->get(StandRepository::class)->findBikesOnStand(self::STAND_ID);
        foreach ($bikes as $bike) {
            $bikeNotes = $this->client->getContainer()->get(NoteRepository::class)->findBikeNote($bike['bikeNum']);
            $this->assertCount($expectedRemainingNotes, $bikeNotes);
        }

        if ($expectedWarning) {
            $this->expectLog(Logger::WARNING, '/Validation error/');
        }
    }

    public static function unTagPatternDataProvider(): array
    {
        return [
            'No pattern' => [
                'pattern' => null,
                'expectedCode' => 'command.untag.success',
                'expectedParams' => [
                    'standName' => self::STAND_NAME,
                    'count' => 2,
                    'hasPattern' => 'false',
                    'pattern' => '',
                ],
                'notifiesAdmins' => true,
                'expectedRemainingNotes' => 0,
                'expectedWarning' => false,
            ],
            'Valid pattern for part of notes' => [
                'pattern' => 'test',
                'expectedCode' => 'command.untag.success',
                'expectedParams' => [
                    'standName' => self::STAND_NAME,
                    'count' => 1,
                    'hasPattern' => 'true',
                    'pattern' => 'test',
                ],
                'notifiesAdmins' => true,
                'expectedRemainingNotes' => 1,
                'expectedWarning' => false,
            ],
            'Invalid pattern' => [
                'pattern' => 'INVALID_PATTERN',
                'expectedCode' => 'command.untag.error.no_notes',
                'expectedParams' => [
                    'standName' => self::STAND_NAME,
                    'hasPattern' => 'true',
                    'pattern' => 'INVALID_PATTERN',
                ],
                'notifiesAdmins' => false,
                'expectedRemainingNotes' => 2,
                'expectedWarning' => true,
            ],
        ];
    }
}
