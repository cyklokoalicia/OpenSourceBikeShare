<?php

declare(strict_types=1);

namespace BikeShare\Repository;

use BikeShare\Db\DbInterface;
use Symfony\Component\Clock\ClockInterface;

class RefreshTokenRepository
{
    public function __construct(
        private readonly DbInterface $db,
        private readonly ClockInterface $clock,
    ) {
    }

    public function store(
        string $plainToken,
        int $userId,
        string $familyId,
        \DateTimeImmutable $expiresAt,
        ?string $userAgent = null,
        ?string $ipAddress = null,
        ?string $parentToken = null
    ): void {
        $tokenHash = $this->hashToken($plainToken);
        $parentTokenHash = $parentToken !== null ? $this->hashToken($parentToken) : null;

        $this->db->query(
            'INSERT INTO api_refresh_tokens (
                tokenHash, userId, familyId, parentTokenHash, replacedByHash, expiresAt,
                createdAt, lastUsedAt, revokedAt, userAgent, ipAddress
            ) VALUES (
                :tokenHash, :userId, :familyId, :parentTokenHash, NULL, :expiresAt,
                :createdAt, NULL, NULL, :userAgent, :ipAddress
            )',
            [
                'tokenHash' => $tokenHash,
                'userId' => $userId,
                'familyId' => $familyId,
                'parentTokenHash' => $parentTokenHash,
                'expiresAt' => $expiresAt->format('Y-m-d H:i:s'),
                'createdAt' => $this->clock->now()->format('Y-m-d H:i:s'),
                'userAgent' => $userAgent,
                'ipAddress' => $ipAddress,
            ]
        );
    }

    public function findActiveByToken(string $plainToken): ?array
    {
        $tokenHash = $this->hashToken($plainToken);

        $result = $this->db->query(
            'SELECT id, userId, familyId, tokenHash, parentTokenHash, replacedByHash, expiresAt, revokedAt
             FROM api_refresh_tokens
             WHERE tokenHash = :tokenHash
             LIMIT 1',
            ['tokenHash' => $tokenHash]
        )->fetchAssoc();

        if ($result === false || $result === null) {
            return null;
        }

        if ($result['revokedAt'] !== null) {
            return null;
        }

        $expiresAt = new \DateTimeImmutable((string)$result['expiresAt']);
        if ($expiresAt <= $this->clock->now()) {
            return null;
        }

        return $result;
    }

    public function rotate(
        string $oldPlainToken,
        string $newPlainToken,
        int $userId,
        string $familyId,
        \DateTimeImmutable $newExpiresAt,
        ?string $userAgent = null,
        ?string $ipAddress = null
    ): void {
        $oldHash = $this->hashToken($oldPlainToken);
        $newHash = $this->hashToken($newPlainToken);
        $now = $this->clock->now()->format('Y-m-d H:i:s');

        $this->db->query(
            'UPDATE api_refresh_tokens
             SET revokedAt = :revokedAt, replacedByHash = :newHash, lastUsedAt = :lastUsedAt
             WHERE tokenHash = :oldHash',
            [
                'revokedAt' => $now,
                'lastUsedAt' => $now,
                'newHash' => $newHash,
                'oldHash' => $oldHash,
            ]
        );

        $this->store($newPlainToken, $userId, $familyId, $newExpiresAt, $userAgent, $ipAddress, $oldPlainToken);
    }

    public function revokeFamily(string $familyId): void
    {
        $this->db->query(
            'UPDATE api_refresh_tokens
             SET revokedAt = :now
             WHERE familyId = :familyId AND revokedAt IS NULL',
            [
                'now' => $this->clock->now()->format('Y-m-d H:i:s'),
                'familyId' => $familyId,
            ]
        );
    }

    public function revokeToken(string $plainToken): void
    {
        $this->db->query(
            'UPDATE api_refresh_tokens
             SET revokedAt = :now
             WHERE tokenHash = :tokenHash AND revokedAt IS NULL',
            [
                'now' => $this->clock->now()->format('Y-m-d H:i:s'),
                'tokenHash' => $this->hashToken($plainToken),
            ]
        );
    }

    private function hashToken(string $token): string
    {
        return hash('sha256', $token);
    }
}
