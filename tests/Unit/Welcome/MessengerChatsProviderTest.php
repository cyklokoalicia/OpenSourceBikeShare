<?php

declare(strict_types=1);

namespace BikeShare\Test\Unit\Welcome;

use BikeShare\Welcome\MessengerChatsProvider;
use BikeShare\Welcome\MessengerIcon;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

class MessengerChatsProviderTest extends TestCase
{
    public function testReturnsEmptyArrayForEmptyJson(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->never())->method('warning');

        $provider = new MessengerChatsProvider('', $logger);

        $this->assertSame([], $provider->getChats());
        $this->assertFalse($provider->hasChats());
    }

    public function testReturnsEmptyArrayForEmptyJsonArray(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->never())->method('warning');

        $provider = new MessengerChatsProvider('[]', $logger);

        $this->assertSame([], $provider->getChats());
        $this->assertFalse($provider->hasChats());
    }

    public function testReturnsEmptyArrayAndLogsWarningOnMalformedJson(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())
            ->method('warning')
            ->with('Failed to parse MESSENGER_CHATS_JSON', $this->anything());

        $provider = new MessengerChatsProvider('{not json}', $logger);

        $this->assertSame([], $provider->getChats());
    }

    public function testReturnsEmptyArrayAndLogsWarningWhenRootIsObject(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())
            ->method('warning')
            ->with('MESSENGER_CHATS_JSON must be a JSON array', $this->anything());

        $provider = new MessengerChatsProvider('{"name":"x","url":"https://t.me/x"}', $logger);

        $this->assertSame([], $provider->getChats());
    }

    public function testParsesValidEntriesAndDetectsIconFromUrl(): void
    {
        $provider = new MessengerChatsProvider(
            '[{"name":"Telegram","url":"https://t.me/example"},'
            . '{"name":"Discord","url":"https://discord.gg/example"}]',
            new NullLogger()
        );

        $chats = $provider->getChats();
        $this->assertCount(2, $chats);
        $this->assertSame('Telegram', $chats[0]->name);
        $this->assertSame('https://t.me/example', $chats[0]->url);
        $this->assertSame(MessengerIcon::TELEGRAM, $chats[0]->icon);
        $this->assertSame(MessengerIcon::DISCORD, $chats[1]->icon);
        $this->assertTrue($provider->hasChats());
    }

    public function testDropsEntryWithInvalidUrlAndKeepsValidOnes(): void
    {
        $provider = new MessengerChatsProvider(
            '[{"name":"OK","url":"https://t.me/ok"},'
            . '{"name":"FTP","url":"ftp://nope"},'
            . '{"name":"JS","url":"javascript:alert(1)"},'
            . '{"name":"Empty","url":""},'
            . '{"name":"OK2","url":"http://example.com/chat"}]',
            new NullLogger()
        );

        $chats = $provider->getChats();
        $this->assertCount(2, $chats);
        $this->assertSame('OK', $chats[0]->name);
        $this->assertSame('OK2', $chats[1]->name);
        $this->assertSame(MessengerIcon::GENERIC, $chats[1]->icon);
    }

    public function testDropsEntryWithMissingName(): void
    {
        $provider = new MessengerChatsProvider(
            '[{"url":"https://t.me/no-name"},'
            . '{"name":"   ","url":"https://t.me/blank"}]',
            new NullLogger()
        );

        $this->assertSame([], $provider->getChats());
    }

    public function testUnknownHostFallsBackToGeneric(): void
    {
        $provider = new MessengerChatsProvider(
            '[{"name":"Mystery","url":"https://example.com/chat"}]',
            new NullLogger()
        );

        $chats = $provider->getChats();
        $this->assertCount(1, $chats);
        $this->assertSame(MessengerIcon::GENERIC, $chats[0]->icon);
    }

    public function testTrimsWhitespaceFromUrl(): void
    {
        $provider = new MessengerChatsProvider(
            '[{"name":"Telegram","url":"  https://t.me/example  "}]',
            new NullLogger()
        );

        $chats = $provider->getChats();
        $this->assertCount(1, $chats);
        $this->assertSame('https://t.me/example', $chats[0]->url);
        $this->assertSame(MessengerIcon::TELEGRAM, $chats[0]->icon);
    }

    public function testIconDetectionIgnoresWwwPrefix(): void
    {
        $provider = new MessengerChatsProvider(
            '[{"name":"WA","url":"https://www.whatsapp.com/group/x"}]',
            new NullLogger()
        );

        $chats = $provider->getChats();
        $this->assertSame(MessengerIcon::WHATSAPP, $chats[0]->icon);
    }

    public function testCachesParseResultAcrossCalls(): void
    {
        $provider = new MessengerChatsProvider(
            '[{"name":"Telegram","url":"https://t.me/x"}]',
            new NullLogger()
        );

        $first = $provider->getChats();
        $second = $provider->getChats();
        $this->assertSame($first, $second);
    }
}
