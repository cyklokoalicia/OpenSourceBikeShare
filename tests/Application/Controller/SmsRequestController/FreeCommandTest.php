<?php

declare(strict_types=1);

namespace BikeShare\Test\Application\Controller\SmsRequestController;

use PHPUnit\Framework\Attributes\DataProvider;
use BikeShare\Repository\BikeRepository;
use BikeShare\Repository\StandRepository;
use BikeShare\Sms\DebugSmsSender;
use BikeShare\Test\Application\BikeSharingWebTestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Translation\TranslatableMessage;

class FreeCommandTest extends BikeSharingWebTestCase
{
    private const USER_PHONE_NUMBER = '421951111111';

    #[DataProvider('freeCommandDataProvider')]
    public function testFreeCommand(
        array $findFreeBikesResult,
        array $findFreeStandsResult,
        array $expectedParams
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

        $smsSender = $this->client->getContainer()->get(DebugSmsSender::class);

        $this->assertCount(1, $smsSender->getSentMessages(), 'Invalid number of sent messages');
        $sentMessage = $smsSender->getSentMessages()[0];

        $this->assertSame(self::USER_PHONE_NUMBER, $sentMessage['number'], 'Invalid response sms number');
        $this->assertInstanceOf(TranslatableMessage::class, $sentMessage['message']);
        $this->assertSame('command.free.message', $sentMessage['message']->getMessage());
        $this->assertSame($expectedParams, $sentMessage['message']->getParameters());
    }

    public static function freeCommandDataProvider(): iterable
    {
        yield 'no free bikes' => [
            'findFreeBikesResult' => [],
            'findFreeStandsResult' => [],
            'expectedParams' => [
                'hasBikes' => 'false',
                'bikesList' => '',
                'hasEmptyStands' => 'false',
                'standsList' => '',
            ],
        ];
        yield 'one stand with free bikes' => [
            'findFreeBikesResult' => [
                [
                    'standName' => 'STAND1',
                    'bikeCount' => 1,
                ],
            ],
            'findFreeStandsResult' => [],
            'expectedParams' => [
                'hasBikes' => 'true',
                'bikesList' => 'STAND1: 1',
                'hasEmptyStands' => 'false',
                'standsList' => '',
            ],
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
            'expectedParams' => [
                'hasBikes' => 'true',
                'bikesList' => "STAND1: 1\nSTAND2: 2",
                'hasEmptyStands' => 'false',
                'standsList' => '',
            ],
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
            'expectedParams' => [
                'hasBikes' => 'true',
                'bikesList' => 'STAND1: 1',
                'hasEmptyStands' => 'true',
                'standsList' => 'STAND3',
            ],
        ];
    }
}
