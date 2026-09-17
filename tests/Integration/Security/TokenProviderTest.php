<?php

declare(strict_types=1);

namespace BikeShare\Test\Integration\Security;

use BikeShare\App\Entity\User;
use BikeShare\App\Security\TokenProvider;
use BikeShare\Db\DbInterface;
use BikeShare\Test\Integration\BikeSharingKernelTestCase;
use Symfony\Component\Security\Core\Authentication\RememberMe\PersistentToken;
use Symfony\Component\Security\Core\Exception\TokenNotFoundException;

class TokenProviderTest extends BikeSharingKernelTestCase
{
    public function testRememberMeTokenRoundTrip(): void
    {
        $db = self::getContainer()->get(DbInterface::class);
        $provider = new TokenProvider($db);
        $series = bin2hex(random_bytes(16));
        $lastUsed = new \DateTimeImmutable('2026-09-17 12:00:00');

        try {
            $provider->createNewToken(new PersistentToken('421951555555', $series, 'initial-token', $lastUsed));

            // Reload through a new provider so the database representation is exercised.
            $provider = new TokenProvider($db);
            $token = $provider->loadTokenBySeries($series);
            self::assertSame('421951555555', $token->getUserIdentifier());
            self::assertSame($series, $token->getSeries());
            self::assertSame('initial-token', $token->getTokenValue());
            self::assertEquals($lastUsed, $token->getLastUsed());
            $row = $db->query(
                'SELECT class FROM remember_me_token WHERE series = :series',
                ['series' => $series]
            )->fetchAssoc();
            self::assertSame(User::class, $row['class']);

            $updatedAt = $lastUsed->modify('+1 minute');
            $provider->updateToken($series, 'rotated-token', $updatedAt);
            $updated = $provider->loadTokenBySeries($series);
            self::assertSame('rotated-token', $updated->getTokenValue());
            self::assertEquals($updatedAt, $updated->getLastUsed());
        } finally {
            $provider->deleteTokenBySeries($series);
        }

        $this->expectException(TokenNotFoundException::class);
        (new TokenProvider($db))->loadTokenBySeries($series);
    }
}
