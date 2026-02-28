<?php

declare(strict_types=1);

namespace BikeShare\Test\Application\Controller\Api\Stand;

use BikeShare\App\Security\UserProvider;
use BikeShare\Db\DbInterface;
use BikeShare\Repository\NoteRepository;
use BikeShare\Repository\StandRepository;
use BikeShare\Test\Application\BikeSharingWebTestCase;
use Symfony\Component\HttpFoundation\Request;

class StandDeleteNoteTest extends BikeSharingWebTestCase
{
    private const ADMIN_PHONE_NUMBER = '421951222222';
    private const STAND_NAME = 'STAND1';

    public function testDeleteNote(): void
    {
        $admin = $this->client->getContainer()->get(UserProvider::class)
            ->loadUserByIdentifier(self::ADMIN_PHONE_NUMBER);
        $this->client->loginUser($admin);

        $this->client->request(Request::METHOD_DELETE, '/api/v1/admin/stands/' . self::STAND_NAME . '/notes');
        $this->assertResponseIsSuccessful();
        $response = $this->decodeApiResponseData();
        $this->assertArrayHasKey('message', $response, 'Response does not contain message key');

        $received = $this->client->getContainer()->get(DbInterface::class)->query(
            'SELECT * FROM received WHERE sender = :sender ORDER BY receive_time DESC, id DESC LIMIT 1',
            ['sender' => self::ADMIN_PHONE_NUMBER]
        )->fetchAssoc();
        $this->assertSame(
            '/api/v1/admin/stands/' . self::STAND_NAME . '/notes',
            $received['sms_text'],
            'Received message is not logged'
        );
        $this->assertEmpty($received['sms_uuid'], 'Web request should not have sms_uuid');

        $sent = $this->client->getContainer()->get(DbInterface::class)->query(
            'SELECT * FROM sent WHERE number = :number ORDER BY time DESC, id DESC LIMIT 1',
            ['number' => self::ADMIN_PHONE_NUMBER]
        )->fetchAssoc();
        $this->assertStringContainsString(
            'note(s) removed successfully',
            $sent['text'],
            'Send message is not logged'
        );

        $stand = $this->client->getContainer()->get(StandRepository::class)->findItemByName(self::STAND_NAME);
        $notes = $this->client->getContainer()->get(NoteRepository::class)->findStandNote($stand['standId']);
        $this->assertEmpty($notes, 'Stand notes were not deleted');
    }
}
