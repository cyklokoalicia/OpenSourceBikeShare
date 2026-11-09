<?php

declare(strict_types=1);

namespace BikeShare\Test\Application;

use BikeShare\Db\DbInterface;
use BikeShare\Rent\Enum\RentSystemType;
use BikeShare\Rent\RentSystemFactory;
use BikeShare\Repository\StandRepository;
use Psr\Container\ContainerInterface;

/**
 * An active stand whose bikes were never returned there, so its stack top is unknown.
 *
 * Fixtures have such stands, but only while no test returns a bike to them: a test that rents
 * from one calls restore() so the stand keeps an unknown stack top for later tests.
 */
class UnknownStackTopStand
{
    private function __construct(
        public readonly int $bike,
        public readonly string $standName,
        private readonly int $maxHistoryId,
    ) {
    }

    public static function find(ContainerInterface $container): ?self
    {
        $db = $container->get(DbInterface::class);
        $standRepository = $container->get(StandRepository::class);
        $stands = $db->query("SELECT standId, standName FROM stands WHERE status = 'active'")->fetchAllAssoc();
        foreach ($stands as $stand) {
            $bikes = $standRepository->findBikesOnStand((int)$stand['standId']);
            if ($bikes !== [] && $standRepository->findLastReturnedBikeOnStand((int)$stand['standId']) === null) {
                $maxHistoryId = $db->query('SELECT COALESCE(MAX(id), 0) AS maxId FROM history')->fetchAssoc();

                return new self((int)$bikes[0]['bikeNum'], $stand['standName'], (int)$maxHistoryId['maxId']);
            }
        }

        return null;
    }

    /**
     * Puts the bike back and drops the history rows written since find(), so the stack top stays unknown.
     */
    public function restore(ContainerInterface $container, int $adminUserId): void
    {
        $container->get(RentSystemFactory::class)->getRentSystem(RentSystemType::WEB)
            ->returnBike($adminUserId, $this->bike, $this->standName, '', true);
        $container->get(DbInterface::class)->query(
            'DELETE FROM history WHERE id > :maxId',
            ['maxId' => $this->maxHistoryId]
        );
    }
}
