<?php

declare(strict_types=1);

namespace BikeShare\Test\Fixtures;

use BikeShare\Db\DbResultInterface;
use BikeShare\Db\PdoDb;

class FailingLedgerDb extends PdoDb
{
    public string $failAfter = '';

    public function query(string $query, array $params = []): DbResultInterface
    {
        $result = parent::query($query, $params);
        if ($this->failAfter !== '' && str_contains($query, $this->failAfter)) {
            throw new \RuntimeException('Injected failure after ' . $this->failAfter);
        }

        return $result;
    }
}
