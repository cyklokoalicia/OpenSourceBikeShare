<?php

declare(strict_types=1);

namespace BikeShare\App\Api;

use Symfony\Component\HttpFoundation\Request;

class ClientVersionDetector
{
    // Pattern: AppName-Android/versionName (versionCode)
    private const ANDROID_UA_PATTERN = '/^.+-Android\/(\d+\.\d+\.\d+)\s*\(\d+\)$/';

    // First Android version that expects new field names (userName)
    private const ANDROID_NEW_FORMAT_MIN_VERSION = '1.0.1';

    public function requiresLegacyFieldNames(Request $request): bool
    {
        $userAgent = $request->headers->get('User-Agent', '');

        if (preg_match(self::ANDROID_UA_PATTERN, $userAgent, $matches)) {
            return version_compare($matches[1], self::ANDROID_NEW_FORMAT_MIN_VERSION, '<');
        }

        // Old Android app without custom UA sends okhttp/*
        if (str_starts_with($userAgent, 'okhttp/')) {
            return true;
        }

        // Browsers, web admin, curl, etc. — new format
        return false;
    }
}
