<?php

declare(strict_types=1);

namespace Application\Controller\SmsRequestController;

use BikeShare\Repository\BikeRepository;
use BikeShare\Repository\StandRepository;
use BikeShare\SmsConnector\SmsConnectorInterface;
use BikeShare\Test\Application\BikeSharingWebTestCase;
use Symfony\Component\HttpFoundation\Request;

class FreeCommandTest extends BikeSharingWebTestCase
{
    private const USER_PHONE_NUMBER = '421111111111';

    /**
     * @dataProvider freeCommandDataProvider
     */
    public function testFreeCommand(
        array $findFreeBikesResult,
        array $findFreeStandsResult,
        string $expectedMessage
    ): void {
        $bikeRepositoryMock = $this->createMock(BikeRepository::class);
        $bikeRepositoryMock
            ->expects($this->once())
            ->method('findFreeBikes')
            ->willReturn($findFreeBikesResult);

        $standRepositoryMock = $this->createMock(StandRepository::class);
        $standRepositoryMock
            ->expects(empty($findFreeBikesResult) ? $this->never() : $this->once())
            ->method('findFreeStands')
            ->willReturn($findFreeStandsResult);

        $this->client->getContainer()->set(BikeRepository::class, $bikeRepositoryMock);
        $this->client->getContainer()->set(StandRepository::class, $standRepositoryMock);

        $this->client->request(
            Request::METHOD_GET,
            '/receive.php',
            [
                'number' => self::USER_PHONE_NUMBER,
                'message' => 'FREE',
                'uuid' => md5((string)microtime(true)),
                'time' => time(),
            ]
        );
        $this->assertResponseIsSuccessful();
        $this->assertSame('', $this->client->getResponse()->getContent());

        $smsConnector = $this->client->getContainer()->get(SmsConnectorInterface::class);

        $this->assertCount(1, $smsConnector->getSentMessages(), 'Invalid number of sent messages');
        $sentMessage = $smsConnector->getSentMessages()[0];

        $this->assertSame(self::USER_PHONE_NUMBER, $sentMessage['number'], 'Invalid response sms number');
        $this->assertSame($expectedMessage, $sentMessage['text'], 'Invalid response sms text');
    }

    public function freeCommandDataProvider(): iterable
    {
        yield 'no free bikes' => [
            'findFreeBikesResult' => [],
            'findFreeStandsResult' => [],
            'expectedMessage' => 'No free bikes.',
        ];
        yield 'one stand with free bikes' => [
            'findFreeBikesResult' => [
                [
                    'standName' => 'STAND1',
                    'bikeCount' => 1,
                ],
            ],
            'findFreeStandsResult' => [],
            'expectedMessage' => 'Free bikes counts:'.PHP_EOL.
                'STAND1: 1',
        ];
        yield 'two stand with free bikes' => [
            'findFreeBikesResult' => [
                [
                    'standName' => 'STAND1',
                    'bikeCount' => 1,
                ],
                [
                    'standName' => 'STAND2',
                    'bikeCount' => 2,
                ],
            ],
            'findFreeStandsResult' => [],
            'expectedMessage' => 'Free bikes counts:' . PHP_EOL .
                'STAND1: 1' . PHP_EOL .
                'STAND2: 2',
        ];
        yield 'empty stands' => [
            'findFreeBikesResult' => [
                [
                    'standName' => 'STAND1',
                    'bikeCount' => 1,
                ],
            ],
            'findFreeStandsResult' => [
                [
                    'standName' => 'STAND3',
                    'bikeCount' => 1,
                ]
            ],
            'expectedMessage' => 'Free bikes counts:' . PHP_EOL .
                'STAND1: 1' . PHP_EOL .
                PHP_EOL .
                'Empty stands:'  . PHP_EOL .
                'STAND3',
        ];
    }
}
