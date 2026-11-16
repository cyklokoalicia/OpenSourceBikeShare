<?php

declare(strict_types=1);

namespace BikeShare\Repository;

use BikeShare\Db\DbInterface;
use Symfony\Component\Clock\ClockInterface;

/**
 * Pending password resets: at most one per user, stored as a SHA-256 hash of the token mailed to them.
 */
class PasswordResetRepository
{
    public function __construct(
        private readonly DbInterface $db,
        private readonly ClockInterface $clock,
    ) {
    }

    /**
     * Replaces any earlier pending reset of the user, so only the latest link works.
     */
    public function save(int $userId, string $tokenHash, int $lifetimeMinutes): void
    {
        $now = $this->clock->now();
        $this->db->query(
            'INSERT INTO passwordReset (userId, tokenHash, expiresAt, createdAt)
             VALUES (:userId, :tokenHash, :expiresAt, :createdAt)
             ON DUPLICATE KEY UPDATE tokenHash = VALUES(tokenHash), expiresAt = VALUES(expiresAt),
                 createdAt = VALUES(createdAt)',
            [
                'userId' => $userId,
                'tokenHash' => $tokenHash,
                'expiresAt' => $now->modify(sprintf('+%d minutes', $lifetimeMinutes))->format('Y-m-d H:i:s'),
                'createdAt' => $now->format('Y-m-d H:i:s'),
            ]
        );
    }

    public function findUserIdByTokenHash(string $tokenHash): ?int
    {
        $row = $this->db->query(
            'SELECT userId FROM passwordReset WHERE tokenHash = :tokenHash AND expiresAt > :now',
            ['tokenHash' => $tokenHash, 'now' => $this->clock->now()->format('Y-m-d H:i:s')]
        )->fetchAssoc();

        return $row ? (int)$row['userId'] : null;
    }

    /**
     * Deletes the user's pending reset if it still matches the token, so that of two concurrent uses only one wins.
     */
    public function consume(int $userId, string $tokenHash): bool
    {
        return $this->db->query(
            'DELETE FROM passwordReset WHERE userId = :userId AND tokenHash = :tokenHash AND expiresAt > :now',
            [
                'userId' => $userId,
                'tokenHash' => $tokenHash,
                'now' => $this->clock->now()->format('Y-m-d H:i:s'),
            ]
        )->rowCount() === 1;
    }

    public function deleteByUserId(int $userId): void
    {
        $this->db->query('DELETE FROM passwordReset WHERE userId = :userId', ['userId' => $userId]);
    }
}
