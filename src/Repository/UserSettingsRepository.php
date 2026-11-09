<?php

declare(strict_types=1);

namespace BikeShare\Repository;

use BikeShare\Db\DbInterface;
use Psr\Log\LoggerInterface;

class UserSettingsRepository
{
    /**
     * @var array{locale: string, allowGeoDetection: bool, showWelcomePage: bool}
     */
    private array $defaultSettings = [
        'locale' => 'en',
        'allowGeoDetection' => false,
        'showWelcomePage' => false,
    ];

    public function __construct(
        private readonly DbInterface $db,
        private readonly string $defaultLocale,
        private readonly LoggerInterface $logger,
    ) {
        $this->defaultSettings['locale'] = $this->defaultLocale;
    }

    /**
     * Always a full set: a missing row, unparsable JSON or a value of the wrong type
     * falls back to the default for that key.
     *
     * @return array{locale: string, allowGeoDetection: bool, showWelcomePage: bool}
     */
    public function findByUserId(int $userId): array
    {
        $row = $this->db->query(
            'SELECT userId, settings FROM userSettings WHERE userId = :userId',
            ['userId' => $userId]
        )->fetchAssoc();

        $stored = [];
        if ($row !== null) {
            try {
                $decoded = json_decode((string) $row['settings'], true, 512, JSON_THROW_ON_ERROR);
                $stored = is_array($decoded) ? $decoded : [];
            } catch (\JsonException $e) {
                $this->logger->error(
                    'Error parsing user settings',
                    ['userId' => $userId, 'settings' => $row['settings'], 'exception' => $e]
                );
            }
        }

        return [
            'locale' => is_string($stored['locale'] ?? null) && $stored['locale'] !== ''
                ? $stored['locale']
                : $this->defaultLocale,
            'allowGeoDetection' => is_bool($stored['allowGeoDetection'] ?? null)
                ? $stored['allowGeoDetection']
                : $this->defaultSettings['allowGeoDetection'],
            'showWelcomePage' => is_bool($stored['showWelcomePage'] ?? null)
                ? $stored['showWelcomePage']
                : $this->defaultSettings['showWelcomePage'],
        ];
    }

    public function saveLocale(int $userId, string $locale): void
    {
        $this->saveSettings($userId, 'locale', $locale);
    }

    public function saveAllowGeoLocation(int $userId, bool $allowGeoDetection): void
    {
        $this->saveSettings($userId, 'allowGeoDetection', $allowGeoDetection);
    }

    public function saveShowWelcomePage(int $userId, bool $value): void
    {
        $this->saveSettings($userId, 'showWelcomePage', $value);
    }

    public function saveSettings(int $userId, string $settingName, mixed $settingValue): void
    {
        $settings = $this->findByUserId($userId);
        $settings[$settingName] = $settingValue;

        $nonDefaults = array_diff_assoc($settings, $this->defaultSettings);

        $this->db->query(
            'INSERT INTO userSettings (userId, settings)
            VALUES (:userId, :settings)
            ON DUPLICATE KEY UPDATE settings = VALUES(settings)',
            [
                'userId' => $userId,
                'settings' => json_encode((object)$nonDefaults, JSON_THROW_ON_ERROR)
            ]
        );
    }
}
