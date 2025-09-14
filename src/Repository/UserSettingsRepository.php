<?php

declare(strict_types=1);

namespace BikeShare\Repository;

use BikeShare\Db\DbInterface;
use Psr\Log\LoggerInterface;

class UserSettingsRepository
{
    private array $defaultSettings = [
        'locale' => 'en',
        'allowGeoDetection' => false,
    ];

    public function __construct(
        private readonly DbInterface $db,
        private readonly string $defaultLocale,
        private readonly LoggerInterface $logger,
    ) {
        $this->defaultSettings['locale'] = $this->defaultLocale;
    }

    public function findByUserId(int $userId): ?array
    {
        $result = $this->db->query('SELECT userId, settings FROM userSettings WHERE userId = :userId', ['userId' => $userId]);
        $row = $result->fetchAssoc();

        if ($row === false || $row === null) {
            return $this->defaultSettings;
        }

        try {
            $settings = json_decode($row['settings'], true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            $settings = [];
            $this->logger->error('Error parsing user settings', ['userId' => $userId, 'settings' => $row['settings'], 'exception' => $e]);
        }
        $settings = array_merge($this->defaultSettings, $settings);

        return $settings;
    }

    public function saveLocale(int $userId, string $locale): void
    {
        $this->saveSettings($userId, 'locale', $locale);
    }

    public function saveAllowGeoLocation(int $userId, bool $allowGeoDetection): void
    {
        $this->saveSettings($userId, 'allowGeoDetection', $allowGeoDetection);
    }

    public function saveSettings(int $userId, string $settingName, $settingValue): void
    {
        $settings = $this->findByUserId($userId);
        $settings[$settingName] = $settingValue;

        $this->db->query(
            'INSERT INTO userSettings (userId, settings) 
            VALUES (:userId, :settings) 
            ON DUPLICATE KEY UPDATE settings = VALUES(settings)',
            [
                'userId' => $userId,
                'settings' => json_encode($settings, JSON_THROW_ON_ERROR)
            ]
        );
    }
}
