<?php

declare(strict_types=1);

namespace BikeShare\App\Api;

use Symfony\Component\HttpFoundation\Request;

class ClientVersionDetector
{
    // Pattern: AppName-Android/versionName (versionCode)
    private const ANDROID_UA_PATTERN = '/^.+-Android\/(\d+\.\d+\.\d+)\s*\(\d+\)$/';

    // Clients at this version get no transforms applied
    private const VERSION_LATEST = '999.0.0';

    // Clients at this version get all transforms applied
    private const VERSION_OLDEST = '0.0.0';

    /**
     * Returns the detected client version as a semver string.
     *
     * - Android with custom UA: parsed version (e.g. "1.0.0")
     * - Old Android (okhttp/*): "0.0.0" (all transforms apply)
     * - Browsers, web admin, etc.: "999.0.0" (no transforms apply)
     */
    public function getClientVersion(Request $request): string
    {
        $userAgent = $request->headers->get('User-Agent', '');

        if (preg_match(self::ANDROID_UA_PATTERN, $userAgent, $matches)) {
            return $matches[1];
        }

        // Old Android app without custom UA sends okhttp/*
        if (str_starts_with($userAgent, 'okhttp/')) {
            return self::VERSION_OLDEST;
        }

        // Browsers, web admin, curl, etc.
        return self::VERSION_LATEST;
    }
}
