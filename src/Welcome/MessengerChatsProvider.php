<?php

declare(strict_types=1);

namespace BikeShare\Welcome;

use Psr\Log\LoggerInterface;

class MessengerChatsProvider
{
    /** @var list<MessengerChat>|null */
    private ?array $chats = null;

    public function __construct(
        private readonly string $rawJson,
        private readonly LoggerInterface $logger,
    ) {
    }

    /**
     * @return list<MessengerChat>
     */
    public function getChats(): array
    {
        if ($this->chats !== null) {
            return $this->chats;
        }

        $raw = trim($this->rawJson);
        if ($raw === '') {
            return $this->chats = [];
        }

        try {
            $decoded = json_decode($raw, true, 16, JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            $this->logger->warning(
                'Failed to parse MESSENGER_CHATS_JSON',
                ['length' => strlen($this->rawJson), 'exception' => $e],
            );

            return $this->chats = [];
        }

        if (!is_array($decoded) || !array_is_list($decoded)) {
            $this->logger->warning(
                'MESSENGER_CHATS_JSON must be a JSON array',
                ['length' => strlen($this->rawJson)],
            );

            return $this->chats = [];
        }

        $parsed = [];
        foreach ($decoded as $entry) {
            $chat = $this->buildChat($entry);
            if ($chat !== null) {
                $parsed[] = $chat;
            }
        }

        return $this->chats = $parsed;
    }

    public function hasChats(): bool
    {
        return $this->getChats() !== [];
    }

    private function buildChat(mixed $entry): ?MessengerChat
    {
        if (!is_array($entry)) {
            $this->logger->warning('Skipping messenger chat entry: not an object');

            return null;
        }

        $name = $entry['name'] ?? null;
        $url = $entry['url'] ?? null;
        if (is_string($url)) {
            $url = trim($url);
        }

        if (!is_string($name) || trim($name) === '') {
            $this->logger->warning('Skipping messenger chat entry: missing name');

            return null;
        }
        if (!is_string($url) || !$this->isValidHttpUrl($url)) {
            $this->logger->warning('Skipping messenger chat entry: invalid url');

            return null;
        }

        return new MessengerChat(
            trim($name),
            $url,
            MessengerIcon::fromUrl($url),
        );
    }

    private function isValidHttpUrl(string $url): bool
    {
        if (filter_var($url, FILTER_VALIDATE_URL) === false) {
            return false;
        }
        $scheme = strtolower((string)parse_url($url, PHP_URL_SCHEME));

        return $scheme === 'http' || $scheme === 'https';
    }
}
