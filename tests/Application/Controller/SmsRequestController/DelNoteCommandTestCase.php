<?php

declare(strict_types=1);

namespace BikeShare\Test\Application\Controller\SmsRequestController;

use BikeShare\Db\DbInterface;
use BikeShare\Mail\MailSenderInterface;
use BikeShare\Repository\NoteRepository;
use BikeShare\Repository\UserRepository;
use BikeShare\SmsConnector\SmsConnectorInterface;
use BikeShare\Test\Application\BikeSharingWebTestCase;
use Monolog\Logger;
use Symfony\Component\HttpFoundation\Request;

class DelNoteCommandTestCase extends BikeSharingWebTestCase
{
    private const USER_PHONE_NUMBER = '421111111111';
    private const ADMIN_PHONE_NUMBER = '421222222222';
    private const BIKE_NUMBER = 2;
    private const STAND_NAME = 'STAND2';
    private const STAND_ID = 2;

    protected function setUp(): void
    {
        parent::setUp();

        $user = $this->client->getContainer()->get(UserRepository::class)
            ->findItemByPhoneNumber(self::USER_PHONE_NUMBER);

        $noteRepository = $this->client->getContainer()->get(NoteRepository::class);
        $noteRepository->deleteBikeNote(self::BIKE_NUMBER, null);
        $noteRepository->deleteStandNote(self::STAND_ID, null);

        $noteRepository->addNoteToBike(self::BIKE_NUMBER, $user['userId'], 'Test note for bike');
        $noteRepository->addNoteToBike(self::BIKE_NUMBER, $user['userId'], 'Note for bike');
        $noteRepository->addNoteToStand(self::STAND_ID, $user['userId'], 'Test note for stand');
        $noteRepository->addNoteToStand(self::STAND_ID, $user['userId'], 'Note for stand');
    }

    /**
     * @dataProvider standNotePatternDataProvider
     */
    public function testDelNoteCommandForStand(
        ?string $pattern,
        string $expectedMessage,
        int $expectedSmsCount,
        int $expectedMailCount,
        int $expectedRemainingNotes,
        bool $expectedWarning
    ): void {
        $standName = self::STAND_NAME;
        $user = $this->client->getContainer()->get(UserRepository::class)
            ->findItemByPhoneNumber(self::ADMIN_PHONE_NUMBER);
    
        $admins = $this->client->getContainer()->get(DbInterface::class)
            ->query('SELECT userId, number,mail FROM users where privileges & 2 != 0')
            ->fetchAllAssoc();
    
        $message = 'DELNOTE ' . $standName;
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
    
        $smsConnector = $this->client->getContainer()->get(SmsConnectorInterface::class);
        $this->assertCount($expectedSmsCount, $smsConnector->getSentMessages());
        $sentMessages = $smsConnector->getSentMessages();
    
        if ($expectedSmsCount === 1) {
            $sentMessage = $sentMessages[0];
            $this->assertSame($expectedMessage, $sentMessage['text'], 'Invalid message text');
            $this->assertSame(self::ADMIN_PHONE_NUMBER, $sentMessage['number'], 'Invalid number');
        } elseif ($expectedSmsCount === 2) {
            $notifiedNumbers = [];
            foreach ($sentMessages as $sentMessage) {
                if ($sentMessage['number'] === self::ADMIN_PHONE_NUMBER) {
                    $this->assertSame(
                        $expectedMessage,
                        $sentMessage['text'],
                        'Invalid message sent to user'
                    );
                } else {
                    $this->assertSame(
                        $user['username'] . ': ' . $expectedMessage,
                        $sentMessage['text'],
                        'Invalid message sent to admin'
                    );
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
        $this->assertCount($expectedMailCount, $mailSender->getSentMessages(), $expectedMailCount === 0 ? 'Unexpected admin email was send' : 'No admin email was send');
        
        if ($expectedMailCount > 0) {
            foreach ($mailSender->getSentMessages() as $sentMessage) {
                $this->assertSame($user['username'] . ': ' . $expectedMessage, $sentMessage['message']);
                $this->assertSame('OpenSourceBikeShare notification', $sentMessage['subject']);
                $this->assertContains($sentMessage['recipient'], array_column($admins, 'mail'));
            }
        }
    
        $standNotes = $this->client->getContainer()->get(NoteRepository::class)->findStandNote(self::STAND_ID);
        $this->assertCount($expectedRemainingNotes, $standNotes);

        if ($expectedWarning) {
            $this->expectLog(Logger::WARNING, '/Validation error/');
        }
    }
    
    public function standNotePatternDataProvider(): array
    {
        return [
            'No pattern' => [
                'pattern' => null,
                'expectedMessage' => 'All 2 notes for stand ' . self::STAND_NAME . ' were deleted.',
                'expectedSmsCount' => 2,
                'expectedMailCount' => 1,
                'expectedRemainingNotes' => 0,
                'expectedWarning' => false,
            ],
            'Valid pattern for one note' => [
                'pattern' => 'test',
                'expectedMessage' => 'One note matching pattern "test" for stand ' . self::STAND_NAME . ' was deleted.',
                'expectedSmsCount' => 2,
                'expectedMailCount' => 1,
                'expectedRemainingNotes' => 1,
                'expectedWarning' => false,
            ],
            'Invalid pattern' => [
                'pattern' => 'INVALID_PATTERN',
                'expectedMessage' => 'No notes matching pattern INVALID_PATTERN found on stand ' . self::STAND_NAME . ' to delete.',
                'expectedSmsCount' => 1,
                'expectedMailCount' => 0,
                'expectedRemainingNotes' => 2,
                'expectedWarning' => true,
            ],
        ];
    }
    
    /**
     * @dataProvider bikeNotePatternDataProvider
     */
    public function testDelNoteCommandForBike(
        ?string $pattern,
        string $expectedMessage,
        int $expectedSmsCount,
        int $expectedMailCount,
        int $expectedRemainingNotes,
        bool $expectedWarning
    ): void {
        $bikeNumber = self::BIKE_NUMBER;
        $user = $this->client->getContainer()->get(UserRepository::class)
            ->findItemByPhoneNumber(self::ADMIN_PHONE_NUMBER);
    
        $admins = $this->client->getContainer()->get(DbInterface::class)
            ->query('SELECT userId, number,mail FROM users where privileges & 2 != 0')
            ->fetchAllAssoc();
    
        $message = 'DELNOTE ' . $bikeNumber;
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
    
        $smsConnector = $this->client->getContainer()->get(SmsConnectorInterface::class);
        $this->assertCount($expectedSmsCount, $smsConnector->getSentMessages());
        $sentMessages = $smsConnector->getSentMessages();
    
        if ($expectedSmsCount === 1) {
            $sentMessage = $sentMessages[0];
            $this->assertSame($expectedMessage, $sentMessage['text'], 'Invalid message sent to user');
            $this->assertSame(self::ADMIN_PHONE_NUMBER, $sentMessage['number'], 'Invalid number');
        } elseif ($expectedSmsCount === 2) {
            $notifiedNumbers = [];
            foreach ($sentMessages as $sentMessage) {
                if ($sentMessage['number'] === self::ADMIN_PHONE_NUMBER) {
                    $this->assertSame(
                        $expectedMessage,
                        $sentMessage['text'],
                        'Invalid message sent to user'
                    );
                } else {
                    $this->assertSame(
                        $user['username'] . ': ' . $expectedMessage,
                        $sentMessage['text'],
                        'Invalid message sent to admin'
                    );
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
        $this->assertCount($expectedMailCount, $mailSender->getSentMessages(), $expectedMailCount === 0 ? 'Unexpected admin email was send' : 'No admin email was send');
        
        if ($expectedMailCount > 0) {
            foreach ($mailSender->getSentMessages() as $sentMessage) {
                $this->assertSame($user['username'] . ': ' . $expectedMessage, $sentMessage['message']);
                $this->assertSame('OpenSourceBikeShare notification', $sentMessage['subject']);
                $this->assertContains($sentMessage['recipient'], array_column($admins, 'mail'));
            }
        }
    
        $bikeNotes = $this->client->getContainer()->get(NoteRepository::class)->findBikeNote($bikeNumber);
        $this->assertCount($expectedRemainingNotes, $bikeNotes);

        if ($expectedWarning) {
            $this->expectLog(Logger::WARNING, '/Validation error/');
        }
    }
    
    public function bikeNotePatternDataProvider(): array
    {
        return [
            'No pattern' => [
                'pattern' => null,
                'expectedMessage' => 'All 2 notes for bike ' . self::BIKE_NUMBER . ' were deleted.',
                'expectedSmsCount' => 2,
                'expectedMailCount' => 1,
                'expectedRemainingNotes' => 0,
                'expectedWarning' => false,
            ],
            'Valid pattern for one note' => [
                'pattern' => 'test',
                'expectedMessage' => 'One note matching pattern "test" for bike ' . self::BIKE_NUMBER . ' was deleted.',
                'expectedSmsCount' => 2,
                'expectedMailCount' => 1,
                'expectedRemainingNotes' => 1,
                'expectedWarning' => false,
            ],
            'Invalid pattern' => [
                'pattern' => 'INVALID_PATTERN',
                'expectedMessage' => 'No notes matching pattern INVALID_PATTERN found for bike ' . self::BIKE_NUMBER . ' to delete.',
                'expectedSmsCount' => 1,
                'expectedMailCount' => 0,
                'expectedRemainingNotes' => 2,
                'expectedWarning' => true,
            ],
        ];
    }
}
