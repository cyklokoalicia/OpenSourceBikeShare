<?php

declare(strict_types=1);

use BikeShare\Db\PdoDb;
use BikeShare\Rent\NormalRentalPlanner;
use BikeShare\Rent\NormalRentalWriter;
use BikeShare\Repository\RentalLedgerRepository;
use Symfony\Component\Clock\MockClock;

require dirname(__DIR__, 2) . '/vendor/autoload.php';

$arguments = $_SERVER['argv'] ?? [];
if (count($arguments) !== 5) {
    throw new \InvalidArgumentException('Expected operation, user ID, bike number and rental ID.');
}

$db = new PdoDb((string)getenv('DB_DSN'), (string)getenv('DB_USER'), (string)getenv('DB_PASSWORD'));
$db->exec('SET SESSION innodb_lock_wait_timeout = 10');
$writer = new NormalRentalWriter($db, new RentalLedgerRepository($db), new NormalRentalPlanner(), new MockClock());
echo $db->query('SELECT CONNECTION_ID() AS id')->fetchAssoc()['id'] . "\n";
fflush(STDOUT);
try {
    $userId = (int)$arguments[2];
    $bikeNum = (int)$arguments[3];
    $effects = null;
    if ($arguments[1] === 'limited-rent') {
        $effects = static function () use ($db, $userId): void {
            $count = $db->query('SELECT COUNT(*) AS n FROM bikes WHERE currentUser = :id', ['id' => $userId]);
            if ((int)$count->fetchAssoc()['n'] > 1) {
                throw new \DomainException('user_limit');
            }
        };
    }
    $result = $arguments[1] === 'return'
        ? $writer->returnBike($userId, $bikeNum, 2, (int)$arguments[4])
        : $writer->rent($userId, $bikeNum, '5678', $effects);
    echo json_encode(['rentId' => $result->rentId, 'eventId' => $result->eventId], JSON_THROW_ON_ERROR) . "\n";
} catch (\DomainException $exception) {
    echo json_encode(['conflict' => $exception->getMessage()], JSON_THROW_ON_ERROR) . "\n";
}
