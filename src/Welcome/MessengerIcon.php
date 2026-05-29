<?php

declare(strict_types=1);

namespace BikeShare\Welcome;

enum MessengerIcon: string
{
    case TELEGRAM = 'telegram';
    case WHATSAPP = 'whatsapp';
    case SIGNAL = 'signal';
    case VIBER = 'viber';
    case DISCORD = 'discord';
    case GENERIC = 'generic';

    private const HOST_MAP = [
        't.me' => self::TELEGRAM,
        'telegram.me' => self::TELEGRAM,
        'telegram.dog' => self::TELEGRAM,
        'wa.me' => self::WHATSAPP,
        'chat.whatsapp.com' => self::WHATSAPP,
        'api.whatsapp.com' => self::WHATSAPP,
        'whatsapp.com' => self::WHATSAPP,
        'signal.group' => self::SIGNAL,
        'signal.me' => self::SIGNAL,
        'invite.viber.com' => self::VIBER,
        'viber.com' => self::VIBER,
        'discord.gg' => self::DISCORD,
        'discord.com' => self::DISCORD,
        'discordapp.com' => self::DISCORD,
    ];

    public static function fromUrl(string $url): self
    {
        $host = strtolower((string)parse_url($url, PHP_URL_HOST));
        if ($host === '') {
            return self::GENERIC;
        }
        $host = preg_replace('/^www\./', '', $host);

        return self::HOST_MAP[$host] ?? self::GENERIC;
    }
}
