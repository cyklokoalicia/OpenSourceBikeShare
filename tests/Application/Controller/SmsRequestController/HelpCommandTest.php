<?php

declare(strict_types=1);

namespace BikeShare\Test\Application\Controller\SmsRequestController;

use PHPUnit\Framework\Attributes\DataProvider;
use BikeShare\Sms\DebugSmsSender;
use BikeShare\Test\Application\BikeSharingWebTestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Translation\TranslatableMessage;

class HelpCommandTest extends BikeSharingWebTestCase
{
    private const USER_PHONE_NUMBER = '421951111111';
    private const ADMIN_PHONE_NUMBER = '421951222222';

    private $smsSystemEnabled;

    protected function setup(): void
    {
        parent::setup();
        $this->smsSystemEnabled = $_ENV['CREDIT_SYSTEM_ENABLED'];
    }

    protected function tearDown(): void
    {
        $_ENV['CREDIT_SYSTEM_ENABLED'] = $this->smsSystemEnabled;
        parent::tearDown();
    }

    #[DataProvider('smsHelpDataProvider')]
    public function testHelpCommand(
        string $phoneNumber,
        array $expectedCommands,
        array $notExpectedCommands,
        bool $isCreditSystemEnabled
    ): void {
        $_ENV['CREDIT_SYSTEM_ENABLED'] = $isCreditSystemEnabled ? '1' : '0';

        $this->client->request(
            Request::METHOD_GET,
            '/receive.php',
            [
                'number' => $phoneNumber,
                'message' => 'HELP',
                'uuid' => md5((string)microtime(true)),
                'time' => time(),
            ]
        );
        $this->assertResponseIsSuccessful();
        $this->assertSame('', $this->client->getResponse()->getContent());
        $smsSender = $this->client->getContainer()->get(DebugSmsSender::class);

        $this->assertCount(1, $smsSender->getSentMessages());
        $sentMessage = $smsSender->getSentMessages()[0];
        $this->assertInstanceOf(TranslatableMessage::class, $sentMessage['message']);
        $this->assertSame('command.help.message', $sentMessage['message']->getMessage());

        $params = $sentMessage['message']->getParameters();
        $this->assertArrayHasKey('commands', $params);
        $commandsList = $params['commands'];

        foreach ($expectedCommands as $command) {
            $this->assertStringContainsString(
                $command,
                $commandsList,
                'Help commands list does not contain expected command'
            );
        }

        foreach ($notExpectedCommands as $command) {
            $this->assertStringNotContainsString(
                $command,
                $commandsList,
                'Help commands list contains unexpected command'
            );
        }

        $this->assertStringContainsString($phoneNumber, $sentMessage['number'], 'Invalid response sms number');
    }

    /**
     * @phpcs:disable Generic.Files.LineLength
     */
    public static function smsHelpDataProvider(): iterable
    {
        yield 'user help' => [
            'phoneNumber' => self::USER_PHONE_NUMBER,
            'expectedCommands' => ['HELP', 'FREE', 'RENT', 'RETURN', 'WHERE', 'INFO', 'NOTE'],
            'notExpectedCommands' => ['FORCERENT', 'FORCERETURN', 'LIST', 'LAST', 'REVERT', 'CODE', 'ADD', 'DELNOTE', 'DELNOTE', 'TAG', 'UNTAG', 'CREDIT'],
            'isCreditSystemEnabled' => false,
        ];
        yield 'admin help' => [
            'phoneNumber' => self::ADMIN_PHONE_NUMBER,
            'expectedCommands' => ['HELP', 'FREE', 'RENT', 'RETURN', 'WHERE', 'INFO', 'NOTE', 'FORCERENT', 'FORCERETURN', 'LIST', 'LAST', 'REVERT', 'CODE', 'ADD', 'DELNOTE', 'DELNOTE', 'TAG', 'UNTAG'],
            'notExpectedCommands' => ['CREDIT'],
            'isCreditSystemEnabled' => false,
        ];
        yield 'user credit help' => [
            'phoneNumber' => self::USER_PHONE_NUMBER,
            'expectedCommands' => ['HELP', 'FREE', 'RENT', 'RETURN', 'WHERE', 'INFO', 'NOTE', 'CREDIT'],
            'notExpectedCommands' => ['FORCERENT', 'FORCERETURN', 'LIST', 'LAST', 'REVERT', 'CODE', 'ADD', 'DELNOTE', 'DELNOTE', 'TAG', 'UNTAG'],
            'isCreditSystemEnabled' => true,
        ];
        yield 'admin credit help' => [
            'phoneNumber' => self::ADMIN_PHONE_NUMBER,
            'expectedCommands' => ['HELP', 'FREE', 'RENT', 'RETURN', 'WHERE', 'INFO', 'NOTE', 'FORCERENT', 'FORCERETURN', 'LIST', 'LAST', 'REVERT', 'CODE', 'ADD', 'DELNOTE', 'DELNOTE', 'TAG', 'UNTAG', 'CREDIT'],
            'notExpectedCommands' => [],
            'isCreditSystemEnabled' => true,
        ];
    }
}
