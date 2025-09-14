<?php

declare(strict_types=1);

namespace BikeShare\Repository;

use BikeShare\Db\DbInterface;

class UserSettingsRepository
{
    public function __construct(private readonly DbInterface $db)
    {
    }

    public function findByUserId(int $userId): ?array
    {
        $result = $this->db->query('SELECT * FROM userSettings WHERE userId = :userId', ['userId' => $userId]);
        $row = $result->fetchAssoc();

        if ($row === false || $row === null) {
            return null;
        }

        if (isset($row['settings'])) {
            $row['settings'] = json_decode($row['settings'], true, 512, JSON_THROW_ON_ERROR);
        }

        return $row;
    }

    public function create(int $userId, array $settings): void
    {
        $this->db->query('INSERT INTO userSettings (userId, settings) VALUES (:userId, :settings)', [
            'userId' => $userId,
            'settings' => json_encode($settings, JSON_THROW_ON_ERROR)
        ]);
    }

    public function update(int $id, array $settings): void
    {
        $this->db->query('UPDATE userSettings SET settings = :settings WHERE id = :id', [
            'id' => $id,
            'settings' => json_encode($settings, JSON_THROW_ON_ERROR)
        ]);
    }
}
