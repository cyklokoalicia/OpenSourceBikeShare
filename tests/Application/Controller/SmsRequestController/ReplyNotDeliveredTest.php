<?php

declare(strict_types=1);

namespace BikeShare\Test\Application\Controller\SmsRequestController;

use BikeShare\Db\DbInterface;
use BikeShare\Mail\MailSenderInterface;
use BikeShare\Repository\UserRepository;
use BikeShare\Repository\UserSettingsRepository;
use BikeShare\Sms\DebugSmsSender;
use BikeShare\SmsConnector\SmsConnectorInterface;
use BikeShare\Test\Application\BikeSharingWebTestCase;
use Monolog\Logger;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Translation\TranslatableMessage;
use Symfony\Contracts\Translation\TranslatorInterface;

class ReplyNotDeliveredTest extends BikeSharingWebTestCase
{
    private const USER_PHONE_NUMBER = '421951111111';
    private const COMMAND = 'WHERE 1';

    protected function setUp(): void
    {
        parent::setUp();
        $this->saveUserLocale('sk');
    }

    protected function tearDown(): void
    {
        $this->saveUserLocale('en');
        parent::tearDown();
    }

    public function testUndeliveredReplyAlertsAdminsWithTheReply(): void
    {
        $this->expectLog(
            Logger::ERROR,
            '/SMS reply not delivered/',
            ['number' => self::USER_PHONE_NUMBER, 'sms' => self::COMMAND]
        );
        $container = $this->client->getContainer();
        $container->get(SmsConnectorInterface::class)->failFor(self::USER_PHONE_NUMBER);
        $sentBefore = $this->countSentRows();

        $this->client->request(
            Request::METHOD_GET,
            '/receive.php',
            [
                'number' => self::USER_PHONE_NUMBER,
                'message' => self::COMMAND,
                'uuid' => md5((string)microtime(true)),
                'time' => time(),
            ],
        );

        $this->assertResponseIsSuccessful();
        $this->assertSame('', $this->client->getResponse()->getContent());
        $this->assertSame($sentBefore, $this->countSentRows(), 'The undelivered reply must not be logged as sent');

        $container = $this->client->getContainer();
        $replies = array_values(array_filter(
            $container->get(DebugSmsSender::class)->getSentMessages(),
            static fn (array $m): bool => $m['number'] === self::USER_PHONE_NUMBER
        ));
        $this->assertCount(1, $replies, 'The reply was attempted');
        $translator = $container->get(TranslatorInterface::class);
        $expectedReply = $replies[0]['message']->trans($translator, 'sk');
        $this->assertNotSame(
            $replies[0]['message']->trans($translator, 'en'),
            $expectedReply,
            'The reply must be rendered in the user locale'
        );

        $mails = $container->get(MailSenderInterface::class)->getSentMessages();
        $alerts = array_values(array_filter(
            $mails,
            static fn (array $m): bool => $m['message'] instanceof TranslatableMessage
                && $m['message']->getMessage() === 'admin.notification.sms_reply_failed'
        ));
        $adminMails = array_values(array_filter(array_column(
            $container->get(UserRepository::class)->findAdmins(),
            'mail'
        )));
        $this->assertNotEmpty($adminMails);
        $this->assertEqualsCanonicalizing($adminMails, array_column($alerts, 'recipient'));
        foreach ($alerts as $alert) {
            $this->assertEquals(new TranslatableMessage('notification.subject.sms_reply_failed'), $alert['subject']);
            $this->assertEquals(
                new TranslatableMessage('admin.notification.sms_reply_failed', [
                    'number' => self::USER_PHONE_NUMBER,
                    'sms' => self::COMMAND,
                    'reply' => $expectedReply,
                ]),
                $alert['message']
            );
        }

        $problems = array_filter(
            $mails,
            static fn (array $m): bool => $m['message'] instanceof TranslatableMessage
                && $m['message']->getMessage() === 'admin.notification.sms_problem'
        );
        $this->assertCount(0, $problems, 'A reply failure is not a processing problem');
    }

    public function testRenderedAlertQuotesTheCommand(): void
    {
        $translator = $this->client->getContainer()->get(TranslatorInterface::class);

        $this->assertSame(
            'Command "WHERE 1" from 1 was processed, but the reply was not delivered: x',
            $translator->trans(
                'admin.notification.sms_reply_failed',
                ['number' => '1', 'sms' => 'WHERE 1', 'reply' => 'x'],
                null,
                'en'
            )
        );
    }

    private function saveUserLocale(string $locale): void
    {
        $container = $this->client->getContainer();
        $user = $container->get(UserRepository::class)->findItemByPhoneNumber(self::USER_PHONE_NUMBER);
        $container->get(UserSettingsRepository::class)->saveLocale((int)$user['userId'], $locale);
    }

    private function countSentRows(): int
    {
        return count($this->client->getContainer()->get(DbInterface::class)
            ->query('SELECT 1 FROM sent WHERE number = :number', ['number' => self::USER_PHONE_NUMBER])
            ->fetchAllAssoc());
    }
}
