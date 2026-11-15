<?php

declare(strict_types=1);

namespace BikeShare\Test\Application\Controller\SmsRequestController;

use BikeShare\Mail\MailSenderInterface;
use BikeShare\Repository\UserRepository;
use BikeShare\Sms\DebugSmsSender;
use BikeShare\Test\Application\BikeSharingWebTestCase;
use Monolog\Logger;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Translation\TranslatableMessage;

class CommandFailedTest extends BikeSharingWebTestCase
{
    private const USER_PHONE_NUMBER = '421951111111';
    private const UNKNOWN_COMMAND = 'Test message';

    public function testFailedCommandAlertsAdminsAsAnSmsProblemOnly(): void
    {
        $this->expectLog(
            Logger::ERROR,
            '/Error processing SMS/',
            ['number' => self::USER_PHONE_NUMBER, 'sms' => self::UNKNOWN_COMMAND]
        );

        $this->client->request(
            Request::METHOD_GET,
            '/receive.php',
            [
                'number' => self::USER_PHONE_NUMBER,
                'message' => self::UNKNOWN_COMMAND,
                'uuid' => md5((string)microtime(true)),
                'time' => time(),
            ],
        );

        $this->assertResponseIsSuccessful();
        $container = $this->client->getContainer();
        $this->assertCount(0, $container->get(DebugSmsSender::class)->getSentMessages(), 'No reply is sent');

        $mails = $container->get(MailSenderInterface::class)->getSentMessages();
        $problems = $this->mailsWithKey($mails, 'admin.notification.sms_problem');
        $adminMails = array_values(array_filter(array_column(
            $container->get(UserRepository::class)->findAdmins(),
            'mail'
        )));
        $this->assertNotEmpty($adminMails);
        $this->assertEqualsCanonicalizing($adminMails, array_column($problems, 'recipient'));
        foreach ($problems as $problem) {
            $this->assertEquals(new TranslatableMessage('notification.subject.sms_problem'), $problem['subject']);
            $this->assertEquals(
                new TranslatableMessage(
                    'admin.notification.sms_problem',
                    ['number' => self::USER_PHONE_NUMBER, 'sms' => self::UNKNOWN_COMMAND]
                ),
                $problem['message']
            );
        }

        $this->assertCount(
            0,
            $this->mailsWithKey($mails, 'admin.notification.sms_reply_failed'),
            'A failed command has no reply to report'
        );
    }

    /**
     * @param array<array<string, mixed>> $mails
     * @return list<array<string, mixed>>
     */
    private function mailsWithKey(array $mails, string $key): array
    {
        return array_values(array_filter(
            $mails,
            static fn (array $m): bool => $m['message'] instanceof TranslatableMessage
                && $m['message']->getMessage() === $key
        ));
    }
}
