<?php

declare(strict_types=1);

namespace BikeShare\Repository;

use BikeShare\Db\DbInterface;
use Symfony\Component\Clock\ClockInterface;

/**
 * History of what each user should have received: SMS replies, QR and API responses.
 */
class SentRepository
{
    /** Length of the `sent.text` column. */
    public const MAX_TEXT_LENGTH = 2000;
    private const TRUNCATION_MARK = '...';

    public function __construct(
        private readonly DbInterface $db,
        private readonly ClockInterface $clock,
    ) {
    }

    public function add(string $number, string $text): void
    {
        if (mb_strlen($text) > self::MAX_TEXT_LENGTH) {
            $text = mb_substr($text, 0, self::MAX_TEXT_LENGTH - mb_strlen(self::TRUNCATION_MARK))
                . self::TRUNCATION_MARK;
        }

        $this->db->query(
            'INSERT INTO sent SET number = :number, text = :text, time = :time',
            [
                'number' => $number,
                'text' => $text,
                'time' => $this->clock->now()->format('Y-m-d H:i:s'),
            ]
        );
    }
}
