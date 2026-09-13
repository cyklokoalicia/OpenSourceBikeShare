<?php

declare(strict_types=1);

namespace BikeShare\Repository;

use BikeShare\Db\DbInterface;

class RentalHistoryBackfillRepository
{
    public function __construct(private readonly DbInterface $db)
    {
    }

    public function iterateEvents(int $toId, ?int $bikeNumber): iterable
    {
        $afterId = 0;
        do {
            $params = ['afterId' => $afterId, 'toId' => $toId];
            $bikeFilter = '';
            if ($bikeNumber !== null) {
                $bikeFilter = ' AND bikeNum = :bikeNum';
                $params['bikeNum'] = $bikeNumber;
            }
            $rows = $this->db->query(
                'SELECT id, bikeNum, userId, action, time, pairActionId FROM history
                 WHERE id > :afterId AND id <= :toId' . $bikeFilter . ' ORDER BY id LIMIT 1000',
                $params,
            )->fetchAllAssoc();
            foreach ($rows as $row) {
                $afterId = (int)$row['id'];
                yield $row;
            }
        } while (count($rows) === 1000);
    }

    public function hasOtherReferences(int $startId, int $returnId): bool
    {
        // Include references outside the requested range and from other bikes.
        return $this->db->query(
            'SELECT id FROM history WHERE pairActionId IN (:startId, :returnId)
             AND id NOT IN (:excludeStartId, :excludeReturnId) LIMIT 1',
            [
                'startId' => $startId,
                'returnId' => $returnId,
                'excludeStartId' => $startId,
                'excludeReturnId' => $returnId,
            ],
        )->fetchAssoc() !== null;
    }

    public function updatePair(array $event, ?int $pairActionId): void
    {
        $affected = $this->db->query(
            'UPDATE history SET pairActionId = :newPair
             WHERE id = :id AND bikeNum = :bikeNum AND userId = :userId AND action = :action
               AND time = :time AND pairActionId <=> :oldPair',
            [
                'newPair' => $pairActionId,
                'id' => $event['id'],
                'bikeNum' => $event['bikeNum'],
                'userId' => $event['userId'],
                'action' => $event['action'],
                'time' => $event['time'],
                'oldPair' => $event['pairActionId'],
            ],
        )->rowCount();
        if ($affected !== 1) {
            throw new \RuntimeException(
                sprintf('History %d changed during backfill; rerun the preview.', $event['id']),
            );
        }
    }
}
