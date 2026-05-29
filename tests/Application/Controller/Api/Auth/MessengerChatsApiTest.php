<?php

declare(strict_types=1);

namespace BikeShare\Test\Application\Controller\Api\Auth;

use BikeShare\Test\Application\BikeSharingWebTestCase;
use Symfony\Component\HttpFoundation\Request;

class MessengerChatsApiTest extends BikeSharingWebTestCase
{
    private const CHATS_JSON = '[{"name":"Newcomers TG","url":"https://t.me/test"},'
        . '{"name":"WhatsApp Group","url":"https://chat.whatsapp.com/test"}]';

    protected function setUp(): void
    {
        $this->setEnvVar('MESSENGER_CHATS_JSON', self::CHATS_JSON);
        parent::setUp();
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
        $this->setEnvVar('MESSENGER_CHATS_JSON', '[]');
        $this->client->restart();

        $this->client->request(Request::METHOD_GET, '/api/v1/auth/messenger-chats');

        $this->assertResponseIsSuccessful();
        $this->assertSame([], $this->decodeApiResponseData());
    }
}
