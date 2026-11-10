<?php

declare(strict_types=1);

namespace BikeShare\Test\Unit\Controller;

use BikeShare\App\Entity\User;
use BikeShare\App\Security\UserProvider;
use BikeShare\Controller\SmsRequestController;
use BikeShare\Notification\AdminRecipients;
use BikeShare\Notification\Notification;
use BikeShare\Notification\NotificationType;
use BikeShare\Notification\NotifierInterface;
use BikeShare\Purifier\PhonePurifierInterface;
use BikeShare\Repository\UserSettingsRepository;
use BikeShare\Sms\SmsSenderInterface;
use BikeShare\SmsCommand\CommandExecutor;
use BikeShare\SmsConnector\SmsConnectorInterface;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\Translation\TranslatableMessage;
use Symfony\Contracts\Translation\TranslatableInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

class SmsRequestControllerTest extends TestCase
{
    public function testReplyThatCannotBeRenderedStillAnswersTheGateway(): void
    {
        $reply = new TranslatableMessage('bike.where.success', ['bad' => new \stdClass()]);
        $translator = $this->createStub(TranslatorInterface::class);
        $translator->method('trans')->willThrowException(new \InvalidArgumentException('cannot format'));

        $connector = $this->createStub(SmsConnectorInterface::class);
        $connector->method('getNumber')->willReturn('421951111111');
        $connector->method('getProcessedMessage')->willReturn('WHERE 1');
        $connector->method('respond')->willReturn('ok:uuid');
        $purifier = $this->createStub(PhonePurifierInterface::class);
        $purifier->method('isValid')->willReturn(true);
        $userProvider = $this->createStub(UserProvider::class);
        $userProvider->method('loadUserByIdentifier')->willReturn(
            new User(4, '421951111111', 'u@example.com', '', 'Bratislava', 'Rider', 0, true, new \DateTimeImmutable())
        );
        $executor = $this->createStub(CommandExecutor::class);
        $executor->method('execute')->willReturn($reply);
        $settings = $this->createStub(UserSettingsRepository::class);
        $settings->method('findByUserId')->willReturn(
            ['locale' => 'sk', 'allowGeoDetection' => false, 'showWelcomePage' => false]
        );
        $smsSender = $this->createStub(SmsSenderInterface::class);
        $smsSender->method('send')->willThrowException(new \InvalidArgumentException('cannot format'));

        $notifier = $this->createMock(NotifierInterface::class);
        $notifier->expects($this->once())->method('notify')->with(
            new Notification(
                NotificationType::SmsReplyFailed,
                new TranslatableMessage('notification.subject.sms_reply_failed'),
                new TranslatableMessage(
                    'admin.notification.sms_reply_failed',
                    ['number' => '421951111111', 'sms' => 'WHERE 1', 'reply' => 'bike.where.success']
                )
            ),
            new AdminRecipients()
        );

        $controller = new SmsRequestController(
            $purifier,
            $connector,
            $smsSender,
            $userProvider,
            $this->createStub(LoggerInterface::class),
            $notifier,
            $executor,
            $settings,
            $translator,
        );

        $this->assertSame('ok:uuid', $controller->index()->getContent());
    }
}
