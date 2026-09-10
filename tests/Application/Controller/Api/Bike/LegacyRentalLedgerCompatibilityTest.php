<?php

declare(strict_types=1);

namespace BikeShare\Test\Application\Controller\Api\Bike;

use BikeShare\App\Security\UserProvider;
use BikeShare\Db\DbInterface;
use BikeShare\Test\Application\BikeSharingWebTestCase;

class LegacyRentalLedgerCompatibilityTest extends BikeSharingWebTestCase
{
    public function testExistingRentalFlowRemainsCompatibleWithLookupIndexes(): void
    {
        $db = $this->client->getContainer()->get(DbInterface::class);
        $db->query('INSERT INTO bikes (bikeNum, currentUser, currentStand, currentCode) VALUES (9913,NULL,1,1234)');
        $this->setEnvVar('WATCHES_NUMBER_TOO_MANY', '9999');
        $user = $this->client->getContainer()->get(UserProvider::class)->loadUserByIdentifier('421951111111');
        $this->client->loginUser($user);
        try {
            $this->client->request('POST', '/api/v1/rentals', ['bikeNumber' => 9913]);
            self::assertResponseIsSuccessful();
            $this->client->request('POST', '/api/v1/returns', ['bikeNumber' => 9913, 'standName' => 'STAND1']);
            self::assertResponseIsSuccessful();
            $rows = $db->query(
                "SELECT action, pairActionId
                 FROM history WHERE bikeNum = 9913 AND action IN ('RENT','RETURN') ORDER BY id"
            )->fetchAllAssoc();
            self::assertSame(['RENT', 'RETURN'], array_column($rows, 'action'));
            self::assertSame([null, null], array_column($rows, 'pairActionId'));
        } finally {
            $db->query('DELETE FROM history WHERE bikeNum = 9913');
            $db->query('DELETE FROM bikes WHERE bikeNum = 9913');
        }
    }
}
