<?php

declare(strict_types=1);

namespace BikeShare\Repository;

use BikeShare\Db\DbInterface;
use Symfony\Component\Clock\ClockInterface;

class UserClientRepository
{
    private const RECORD_THROTTLE = 'PT1H';

    public function __construct(
        private readonly DbInterface $db,
        private readonly ClockInterface $clock,
    ) {
    }

    /**
     * @return list<array{platform: string, version: string, lastSeenAt: string}>
     */
    public function findByUserId(int $userId): array
    {
        return $this->db->query(
            'SELECT platform, version, lastSeenAt
              FROM userClient
              WHERE userId = :userId
              ORDER BY lastSeenAt DESC',
            ['userId' => $userId]
        )->fetchAllAssoc();
    }

    public function recordSeen(int $userId, string $platform, string $version): void
    {
        $now = $this->clock->now();
        $throttleCutoff = $now->sub(new \DateInterval(self::RECORD_THROTTLE));

        // Updates lastSeenAt first so its IF-condition can read the original `version` column
        // before the second assignment (potentially) overwrites it.
        $this->db->query(
            'INSERT INTO userClient (userId, platform, version, lastSeenAt)
              VALUES (:userId, :platform, :version, :now)
              ON DUPLICATE KEY UPDATE
                lastSeenAt = IF(version <> VALUES(version) OR lastSeenAt < :throttleCutoff,
                                VALUES(lastSeenAt), lastSeenAt),
                version = IF(VALUES(version) <> version, VALUES(version), version)',
            [
                'userId' => $userId,
                'platform' => $platform,
                'version' => $version,
                'now' => $now->format('Y-m-d H:i:s'),
                'throttleCutoff' => $throttleCutoff->format('Y-m-d H:i:s'),
            ]
        );
    }
}
