<?php

declare(strict_types=1);

namespace BikeShare\Test\Support;

use PHPUnit\Framework\Assert;
use Symfony\Component\Translation\TranslatableMessage;

/**
 * Assertions on mails captured by DebugMailSender::getSentMessages().
 */
class MailAssert
{
    /**
     * Returns the path of the e-mail confirmation link carried by a captured mail,
     * after checking the mail body is the expected translation.
     *
     * @param array{message: mixed} $mail
     */
    public static function confirmationLink(array $mail, string $expectedKey): string
    {
        $message = $mail['message'] ?? null;
        Assert::assertInstanceOf(TranslatableMessage::class, $message);
        Assert::assertSame($expectedKey, $message->getMessage());
        $link = parse_url((string)($message->getParameters()['emailConfirmURL'] ?? ''), PHP_URL_PATH);
        Assert::assertIsString($link, 'Email confirmation link not found');
        Assert::assertNotSame('', $link, 'Email confirmation link not found');

        return $link;
    }

    /**
     * Asserts a captured mail is the admin audit copy of an SMS command result.
     * The recipient is left to the caller, which knows the admin list.
     *
     * @param array{message: mixed, subject: mixed} $mail
     */
    public static function smsAudit(array $mail, string $userName, TranslatableMessage $resultMessage): void
    {
        Assert::assertEquals(
            new TranslatableMessage('admin.notification.sms_processed', [
                'userName' => $userName,
                'message' => $resultMessage,
            ]),
            $mail['message']
        );
        Assert::assertEquals(new TranslatableMessage('notification.subject.sms_command_audit'), $mail['subject']);
    }
}
