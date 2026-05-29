<?php

declare(strict_types=1);

namespace BikeShare\Test\Application\Controller\Api\Auth;

use BikeShare\Test\Application\BikeSharingWebTestCase;
use Symfony\Component\HttpFoundation\Request;

class MessengerChatsApiTest extends BikeSharingWebTestCase
{
    private const CHATS_JSON = '[{"name":"Newcomers TG","url":"https://t.me/test"},'
        . '{"name":"WhatsApp Group","url":"https://chat.whatsapp.com/test"}]';

    private ?string $previousChatsEnv = null;

    protected function setUp(): void
    {
        $this->previousChatsEnv = $_SERVER['MESSENGER_CHATS_JSON'] ?? '';
        $_SERVER['MESSENGER_CHATS_JSON'] = self::CHATS_JSON;
        $_ENV['MESSENGER_CHATS_JSON'] = self::CHATS_JSON;

        parent::setUp();
    }

    protected function tearDown(): void
    {
        $_SERVER['MESSENGER_CHATS_JSON'] = $this->previousChatsEnv;
        $_ENV['MESSENGER_CHATS_JSON'] = $this->previousChatsEnv;
        parent::tearDown();
    }

    public function testReturnsConfiguredChatsWithoutAuthentication(): void
    {
        $this->client->request(Request::METHOD_GET, '/api/v1/auth/messenger-chats');

        $this->assertResponseIsSuccessful();
        $chats = $this->decodeApiResponseData();

        $this->assertCount(2, $chats);
        $this->assertSame('Newcomers TG', $chats[0]['name']);
        $this->assertSame('https://t.me/test', $chats[0]['url']);
        $this->assertSame('telegram', $chats[0]['icon']);
        $this->assertSame('whatsapp', $chats[1]['icon']);
    }

    public function testReturnsEmptyListWhenNoChatsConfigured(): void
    {
        $_SERVER['MESSENGER_CHATS_JSON'] = '[]';
        $_ENV['MESSENGER_CHATS_JSON'] = '[]';
        self::ensureKernelShutdown();
        $this->client = static::createClient();

        $this->client->request(Request::METHOD_GET, '/api/v1/auth/messenger-chats');

        $this->assertResponseIsSuccessful();
        $this->assertSame([], $this->decodeApiResponseData());
    }
}
