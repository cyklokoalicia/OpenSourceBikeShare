<?php

declare(strict_types=1);

namespace BikeShare\Test\Application\Controller\SmsRequestController;

use BikeShare\Db\DbInterface;
use BikeShare\Repository\UserRepository;
use BikeShare\Sms\DebugSmsSender;
use BikeShare\Test\Application\BikeSharingWebTestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Translation\TranslatableMessage;

class CodeCommandTest extends BikeSharingWebTestCase
{
    private const ADMIN_PHONE_NUMBER = '421951222222';
    private const BIKE_NUMBER = 3;

    public function testAdminCanSetBikeCode(): void
    {
        $user = $this->client->getContainer()->get(UserRepository::class)
            ->findItemByPhoneNumber(self::ADMIN_PHONE_NUMBER);

        $db = $this->client->getContainer()->get(DbInterface::class);
        $bikeRow = $db->query(
            'SELECT currentCode FROM bikes WHERE bikeNum = :bikeNumber',
            ['bikeNumber' => self::BIKE_NUMBER]
        )->fetchAssoc();

        $currentCode = str_pad((string)$bikeRow['currentCode'], 4, '0', STR_PAD_LEFT);
        $newCode = '4321';
        if ($newCode === $currentCode) {
            $newCode = '1234';
        }

        $this->client->request(
            Request::METHOD_GET,
            '/receive.php',
            [
                'number' => self::ADMIN_PHONE_NUMBER,
                'message' => sprintf('CODE %d %d', self::BIKE_NUMBER, $newCode),
                'uuid' => md5((string) microtime(true)),
                'time' => time(),
            ]
        );
        $this->assertResponseIsSuccessful();
        $this->assertSame('', $this->client->getResponse()->getContent());

        $smsSender = $this->client->getContainer()->get(DebugSmsSender::class);
        $this->assertCount(1, $smsSender->getSentMessages(), 'Invalid number of sent messages');
        $sentMessage = $smsSender->getSentMessages()[0];

        $this->assertSame(self::ADMIN_PHONE_NUMBER, $sentMessage['number'], 'Invalid response sms number');
        $this->assertInstanceOf(TranslatableMessage::class, $sentMessage['message']);
        $this->assertSame('command.code.success', $sentMessage['message']->getMessage());
        $this->assertSame(
            ['bikeNumber' => self::BIKE_NUMBER, 'code' => $newCode],
            $sentMessage['message']->getParameters()
        );

        $updatedBike = $db->query(
            'SELECT currentCode FROM bikes WHERE bikeNum = :bikeNumber',
            ['bikeNumber' => self::BIKE_NUMBER]
        )->fetchAssoc();
        $this->assertSame(
            $newCode,
            str_pad((string)$updatedBike['currentCode'], 4, '0', STR_PAD_LEFT),
            'Bike code was not updated'
        );

        $history = $db->query(
            'SELECT
                action, parameter 
             FROM history 
             WHERE userId = :userId 
                AND bikeNum = :bikeNum 
             ORDER BY id DESC 
             LIMIT 1',
            [
                'userId' => $user['userId'],
                'bikeNum' => self::BIKE_NUMBER,
            ]
        )->fetchAssoc();

        $this->assertSame('CHANGECODE', $history['action'], 'Invalid history action');
        $this->assertSame($newCode, $history['parameter'], 'Invalid history parameter');
    }
}
