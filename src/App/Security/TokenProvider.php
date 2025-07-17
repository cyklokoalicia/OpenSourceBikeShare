<?php

declare(strict_types=1);

namespace BikeShare\App\Security;

use BikeShare\Db\DbInterface;
use Symfony\Component\Security\Core\Authentication\RememberMe\PersistentToken;
use Symfony\Component\Security\Core\Authentication\RememberMe\PersistentTokenInterface;
use Symfony\Component\Security\Core\Authentication\RememberMe\TokenProviderInterface;
use Symfony\Component\Security\Core\Exception\TokenNotFoundException;

class TokenProvider implements TokenProviderInterface
{
    private $tokens = [];
    private DbInterface $db;

    public function __construct(
        DbInterface $db
    ) {
        $this->db = $db;
    }

    public function loadTokenBySeries(string $series): PersistentTokenInterface
    {
        if (!isset($this->tokens[$series])) {
            $result = $this->db->query(
                'SELECT * FROM remember_me_token WHERE series = :series',
                ['series' => $series]
            );
            if (!$result || $result->rowCount() == 0) {
                throw new TokenNotFoundException('No token found.');
            }

            $row = $result->fetchAssoc();

            $this->tokens[$series] = new PersistentToken(
                $row['class'],
                $row['username'],
                $row['series'],
                $row['value'],
                new \DateTime($row['lastUsed'])
            );
        }

        return $this->tokens[$series];
    }

    /**
     * {@inheritdoc}
     */
    public function updateToken(string $series, string $tokenValue, \DateTime $lastUsed)
    {
        $currentToken = $this->loadTokenBySeries($series);

        $token = new PersistentToken(
            $currentToken->getClass(),
            $currentToken->getUserIdentifier(),
            $series,
            $tokenValue,
            $lastUsed
        );
        $this->tokens[$series] = $token;
    }

    /**
     * {@inheritdoc}
     */
    public function deleteTokenBySeries(string $series)
    {
        $this->db->query(
            'DELETE FROM remember_me_token WHERE series= :series',
            ['series' => $series]
        );

        unset($this->tokens[$series]);
    }

    /**
     * {@inheritdoc}
     */
    public function createNewToken(PersistentTokenInterface $token)
    {
        $this->db->query(
            'INSERT INTO remember_me_token (class, username, series, value, lastUsed) 
             VALUES (:class, :username, :series, :value, :lastUsed)',
            [
                'class' => $token->getClass(),
                'username' => $token->getUserIdentifier(),
                'series' => $token->getSeries(),
                'value' => $token->getTokenValue(),
                'lastUsed' => $token->getLastUsed()->format('Y-m-d H:i:s'),
            ]
        );

        $this->tokens[$token->getSeries()] = $token;
    }
}
