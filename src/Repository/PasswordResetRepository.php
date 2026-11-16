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
    public function save(int $userId, string $plainToken, int $lifetimeMinutes): void
    {
        $this->db->query(
            'INSERT INTO passwordReset (userId, tokenHash, expiresAt) VALUES (:userId, :tokenHash, :expiresAt)
             ON DUPLICATE KEY UPDATE tokenHash = VALUES(tokenHash), expiresAt = VALUES(expiresAt)',
            [
                'userId' => $userId,
                'tokenHash' => $this->hashToken($plainToken),
                'expiresAt' => $this->clock->now()
                    ->modify(sprintf('+%d minutes', $lifetimeMinutes))
                    ->format('Y-m-d H:i:s'),
            ]
        );
    }

    public function findUserIdByToken(string $plainToken): ?int
    {
        $row = $this->db->query(
            'SELECT userId FROM passwordReset WHERE tokenHash = :tokenHash AND expiresAt > :now',
            ['tokenHash' => $this->hashToken($plainToken), 'now' => $this->now()]
        )->fetchAssoc();

        return $row ? (int)$row['userId'] : null;
    }

    /**
     * Deletes the pending reset if the token is still valid, so that of two concurrent uses only one wins.
     */
    public function consume(string $plainToken): bool
    {
        return $this->db->query(
            'DELETE FROM passwordReset WHERE tokenHash = :tokenHash AND expiresAt > :now',
            ['tokenHash' => $this->hashToken($plainToken), 'now' => $this->now()]
        )->rowCount() === 1;
    }

    public function deleteByUserId(int $userId): void
    {
        $this->db->query('DELETE FROM passwordReset WHERE userId = :userId', ['userId' => $userId]);
    }

    private function hashToken(string $plainToken): string
    {
        return hash('sha256', $plainToken);
    }

    private function now(): string
    {
        return $this->clock->now()->format('Y-m-d H:i:s');
    }
}
