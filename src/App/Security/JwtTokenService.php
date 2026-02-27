<?php

declare(strict_types=1);

namespace BikeShare\App\Security;

use BikeShare\App\Entity\User;
use Lcobucci\JWT\Builder;
use Lcobucci\JWT\Configuration;
use Lcobucci\JWT\Signer\Hmac\Sha256;
use Lcobucci\JWT\Signer\Key\InMemory;
use Lcobucci\JWT\UnencryptedToken;
use Lcobucci\JWT\Validation\Constraint\SignedWith;
use Symfony\Component\Clock\ClockInterface;

class JwtTokenService
{
    private Configuration $configuration;
    private string $activeKeyId;
    /** @var array<string, InMemory> */
    private array $verificationKeys = [];

    public function __construct(
        private readonly string $secret,
        private readonly int $accessTtlSeconds,
        private readonly int $refreshTtlSeconds,
        private readonly ClockInterface $clock,
        string $activeKeyId = 'v1',
        array $keys = [],
    ) {
        $this->activeKeyId = $this->normalizeActiveKeyId($activeKeyId);
        $resolvedKeys = $this->normalizeKeys($keys);
        if (!array_key_exists($this->activeKeyId, $resolvedKeys)) {
            $resolvedKeys[$this->activeKeyId] = $this->secret;
        }

        $this->verificationKeys = $this->buildVerificationKeys($resolvedKeys);
        $this->configuration = Configuration::forSymmetricSigner(
            new Sha256(),
            $this->verificationKeys[$this->activeKeyId],
        );
    }

    public function createAccessToken(User $user): array
    {
        $now = $this->clock->now();
        $expiresAt = $now->add(new \DateInterval('PT' . $this->accessTtlSeconds . 'S'));
        $token = $this->buildBaseToken($user, $now, $expiresAt)
            ->withClaim('typ', 'access')
            ->withClaim('number', $user->getNumber())
            ->withClaim('roles', $user->getRoles())
            ->getToken($this->configuration->signer(), $this->configuration->signingKey())
            ->toString();

        return [
            'token' => $token,
            'expiresAt' => $expiresAt,
            'expiresIn' => $this->accessTtlSeconds,
        ];
    }

    public function createRefreshToken(User $user, string $familyId): array
    {
        $now = $this->clock->now();
        $expiresAt = $now->add(new \DateInterval('PT' . $this->refreshTtlSeconds . 'S'));
        $token = $this->buildBaseToken($user, $now, $expiresAt)
            ->withClaim('typ', 'refresh')
            ->withClaim('number', $user->getNumber())
            ->withClaim('family', $familyId)
            ->getToken($this->configuration->signer(), $this->configuration->signingKey())
            ->toString();

        return [
            'token' => $token,
            'expiresAt' => $expiresAt,
            'expiresIn' => $this->refreshTtlSeconds,
        ];
    }

    public function decodeAndValidate(string $jwt, string $expectedType): array
    {
        $token = $this->parseToken($jwt);
        if (
            $token->headers()->get('alg') !== 'HS256'
            || $token->headers()->get('typ') !== 'JWT'
        ) {
            throw new \InvalidArgumentException('Unsupported JWT algorithm.');
        }

        if (!$this->isSignatureValid($token)) {
            throw new \InvalidArgumentException('Invalid JWT signature.');
        }

        $claims = $token->claims();
        if ($claims->get('typ') !== $expectedType) {
            throw new \InvalidArgumentException('Unexpected JWT token type.');
        }

        $expiration = $claims->get('exp');
        if (!$expiration instanceof \DateTimeInterface) {
            throw new \InvalidArgumentException('JWT token expiration is missing.');
        }

        if ($expiration->getTimestamp() < $this->clock->now()->getTimestamp()) {
            throw new \InvalidArgumentException('JWT token expired.');
        }

        return $this->normalizeClaims($token);
    }

    private function buildBaseToken(
        User $user,
        \DateTimeImmutable $issuedAt,
        \DateTimeImmutable $expiresAt
    ): Builder {
        return $this->configuration->builder()
            ->withHeader('typ', 'JWT')
            ->withHeader('kid', $this->activeKeyId)
            ->identifiedBy($this->generateTokenId())
            ->issuedAt($issuedAt)
            ->expiresAt($expiresAt)
            ->relatedTo((string)$user->getUserId());
    }

    private function parseToken(string $jwt): UnencryptedToken
    {
        try {
            $token = $this->configuration->parser()->parse($jwt);
        } catch (\Throwable $e) {
            throw new \InvalidArgumentException('Malformed JWT.', 0, $e);
        }

        if (!$token instanceof UnencryptedToken) {
            throw new \InvalidArgumentException('Malformed JWT.');
        }

        return $token;
    }

    private function isSignatureValid(UnencryptedToken $token): bool
    {
        $tokenKeyId = $token->headers()->get('kid');
        if (is_string($tokenKeyId)) {
            if (!isset($this->verificationKeys[$tokenKeyId])) {
                return false;
            }

            return $this->isSignatureValidWithKey($token, $this->verificationKeys[$tokenKeyId]);
        }

        // Backward compatibility for tokens issued before key identifiers.
        foreach ($this->verificationKeys as $verificationKey) {
            if ($this->isSignatureValidWithKey($token, $verificationKey)) {
                return true;
            }
        }

        return false;
    }

    private function normalizeClaims(UnencryptedToken $token): array
    {
        $claims = $token->claims()->all();
        foreach (['iat', 'nbf', 'exp'] as $dateClaim) {
            if (($claims[$dateClaim] ?? null) instanceof \DateTimeInterface) {
                $claims[$dateClaim] = $claims[$dateClaim]->getTimestamp();
            }
        }

        return $claims;
    }

    private function generateTokenId(): string
    {
        return bin2hex(random_bytes(16));
    }

    /**
     * @param array<mixed> $keys
     *
     * @return array<string, string>
     */
    private function normalizeKeys(array $keys): array
    {
        $normalized = [];
        foreach ($keys as $keyId => $keySecret) {
            if (!is_string($keyId) || trim($keyId) === '') {
                throw new \InvalidArgumentException('JWT key id must be a non-empty string.');
            }

            if (!is_string($keySecret) || trim($keySecret) === '') {
                throw new \InvalidArgumentException(
                    sprintf('JWT secret for key "%s" must be a non-empty string.', $keyId)
                );
            }

            $normalized[trim($keyId)] = $keySecret;
        }

        return $normalized;
    }

    private function normalizeActiveKeyId(string $activeKeyId): string
    {
        $normalized = trim($activeKeyId);
        if ($normalized === '') {
            throw new \InvalidArgumentException('Active JWT key id must be a non-empty string.');
        }

        return $normalized;
    }

    /**
     * @param array<string, string> $resolvedKeys
     *
     * @return array<string, InMemory>
     */
    private function buildVerificationKeys(array $resolvedKeys): array
    {
        $verificationKeys = [];
        foreach ($resolvedKeys as $keyId => $keySecret) {
            $verificationKeys[$keyId] = InMemory::plainText($keySecret);
        }

        return $verificationKeys;
    }

    private function isSignatureValidWithKey(UnencryptedToken $token, InMemory $verificationKey): bool
    {
        return $this->configuration->validator()->validate(
            $token,
            new SignedWith($this->configuration->signer(), $verificationKey),
        );
    }
}
