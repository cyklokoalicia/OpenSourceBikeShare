<?php

declare(strict_types=1);

namespace BikeShare\Test\Unit\Notifier;

use BikeShare\Db\DbInterface;
use BikeShare\Db\DbResultInterface;
use BikeShare\Mail\MailSenderInterface;
use BikeShare\Notifier\AdminNotifier;
use BikeShare\Repository\UserSettingsRepository;
use BikeShare\Sms\SmsSenderInterface;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Translation\TranslatableMessage;
use Symfony\Contracts\Translation\TranslatorInterface;

#[AllowMockObjectsWithoutExpectations]
class AdminNotifierTest extends TestCase
{
    private DbInterface&MockObject $dbMock;
    private MailSenderInterface&MockObject $mailerMock;
    private SmsSenderInterface&MockObject $smsSenderMock;
    private TranslatorInterface&MockObject $translatorMock;
    private UserSettingsRepository&MockObject $userSettingsRepositoryMock;
    private AdminNotifier $notifier;

    protected function setUp(): void
    {
        $this->dbMock = $this->createMock(DbInterface::class);
        $this->mailerMock = $this->createMock(MailSenderInterface::class);
        $this->smsSenderMock = $this->createMock(SmsSenderInterface::class);
        $this->translatorMock = $this->createMock(TranslatorInterface::class);
        $this->userSettingsRepositoryMock = $this->createMock(UserSettingsRepository::class);

        $this->notifier = new AdminNotifier(
            'BikeShare',
            $this->dbMock,
            $this->mailerMock,
            $this->smsSenderMock,
            $this->translatorMock,
            $this->userSettingsRepositoryMock,
        );
    }

    protected function tearDown(): void
    {
        unset(
            $this->dbMock,
            $this->mailerMock,
            $this->smsSenderMock,
            $this->translatorMock,
            $this->userSettingsRepositoryMock,
            $this->notifier,
        );
    }

    public function testNotifyRendersInEachAdminLocale(): void
    {
        $this->mockAdmins([
            ['userId' => 1, 'number' => '111', 'mail' => 'a@x'],
            ['userId' => 2, 'number' => '222', 'mail' => 'b@x'],
        ]);

        $this->userSettingsRepositoryMock
            ->method('findByUserId')
            ->willReturnMap([
                [1, ['locale' => 'en', 'allowGeoDetection' => false]],
                [2, ['locale' => 'de', 'allowGeoDetection' => false]],
            ]);

        $this->translatorMock
            ->method('trans')
            ->willReturnCallback(
                static function (string $key, array $params, ?string $domain, ?string $locale): string {
                    return match (true) {
                        $key === 'admin.notification.subject' && $locale === 'en' => 'BikeShare notification',
                        $key === 'admin.notification.subject' && $locale === 'de' => 'BikeShare Benachrichtigung',
                        $key === 'admin.test' && $locale === 'en' => 'Hello admin',
                        $key === 'admin.test' && $locale === 'de' => 'Hallo Admin',
                        default => '!',
                    };
                }
            );

        $smsCalls = [];
        $this->smsSenderMock
            ->expects($this->exactly(2))
            ->method('send')
            ->willReturnCallback(
                static function (string $number, $message, ?string $locale) use (&$smsCalls): void {
                    $smsCalls[$number] = $locale;
                }
            );

        $mailCalls = [];
        $this->mailerMock->expects($this->exactly(2))->method('sendMail')
            ->willReturnCallback(static function (string $to, string $subject, string $body) use (&$mailCalls): void {
                $mailCalls[$to] = ['subject' => $subject, 'body' => $body];
            });

        $this->notifier->notify(new TranslatableMessage('admin.test'));

        $this->assertSame('en', $smsCalls['111']);
        $this->assertSame('de', $smsCalls['222']);
        $this->assertSame(['subject' => 'BikeShare notification', 'body' => 'Hello admin'], $mailCalls['a@x']);
        $this->assertSame(['subject' => 'BikeShare Benachrichtigung', 'body' => 'Hallo Admin'], $mailCalls['b@x']);
    }

    public function testNotifySkipsExcludedAdmins(): void
    {
        $this->mockAdmins([
            ['userId' => 1, 'number' => '111', 'mail' => 'a@x'],
            ['userId' => 2, 'number' => '222', 'mail' => 'b@x'],
        ]);

        $this->userSettingsRepositoryMock->method('findByUserId')->willReturn(['locale' => 'en']);
        $this->translatorMock->method('trans')->willReturn('msg');

        $this->smsSenderMock->expects($this->once())->method('send')->with('222');
        $this->mailerMock->expects($this->once())->method('sendMail')->with('b@x');

        $this->notifier->notify(new TranslatableMessage('admin.test'), true, [1]);
    }

    public function testNotifyByMailOnlyWhenSmsDisabled(): void
    {
        $this->mockAdmins([['userId' => 1, 'number' => '111', 'mail' => 'a@x']]);
        $this->userSettingsRepositoryMock->method('findByUserId')->willReturn(['locale' => 'en']);
        $this->translatorMock->method('trans')->willReturn('msg');

        $this->smsSenderMock->expects($this->never())->method('send');
        $this->mailerMock->expects($this->once())->method('sendMail');

        $this->notifier->notify(new TranslatableMessage('admin.test'), false);
    }

    public function testNotifyFallsBackToDefaultLocaleWhenSettingsMissing(): void
    {
        $this->mockAdmins([['userId' => 1, 'number' => '111', 'mail' => 'a@x']]);
        $this->userSettingsRepositoryMock->method('findByUserId')->willReturn([]);

        $localeSeen = null;
        $this->translatorMock
            ->method('trans')
            ->willReturnCallback(static function ($k, $p, $d, $l) use (&$localeSeen): string {
                $localeSeen = $l;
                return 'msg';
            });

        $this->smsSenderMock->expects($this->once())->method('send');
        $this->mailerMock->expects($this->once())->method('sendMail');

        $this->notifier->notify(new TranslatableMessage('admin.test'));

        $this->assertNull(
            $localeSeen,
            'Locale should be null when missing in settings — translator falls back to default'
        );
    }

    private function mockAdmins(array $admins): void
    {
        $resultMock = $this->createMock(DbResultInterface::class);
        $resultMock->method('fetchAllAssoc')->willReturn($admins);
        $this->dbMock->method('query')->willReturn($resultMock);
    }
}
