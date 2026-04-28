<?php

declare(strict_types=1);

namespace BikeShare\Test\Application\Controller;

use PHPUnit\Framework\Attributes\DataProvider;
use BikeShare\App\Security\UserProvider;
use BikeShare\Db\DbInterface;
use BikeShare\Repository\UserSettingsRepository;
use BikeShare\Sms\DebugSmsSender;
use BikeShare\Test\Application\BikeSharingWebTestCase;
use Monolog\Logger;
use PHPUnit\Framework\Constraint\Callback;
use PHPUnit\Framework\Constraint\Constraint;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Contracts\Translation\TranslatorInterface;

class SmsRequestControllerTest extends BikeSharingWebTestCase
{
    private const USER_PHONE_NUMBER = '421951111111';

    /**
     * @var Callback|string $expectedSms
     */
    #[DataProvider('smsDataProvider')]
    public function testBaseSmsFlow(
        string $phoneNumber,
        string $message,
        string $expectedResponse,
        int $expectedResponseCode,
        string|Constraint|null $expectedSms,
        array $expectedLog
    ): void {
        $smsUuid = md5((string)microtime(true));

        $this->client->request(
            Request::METHOD_GET,
            '/receive.php',
            [
                'number' => $phoneNumber,
                'message' => $message,
                'uuid' => $smsUuid,
                'time' => time(),
            ]
        );
        $this->assertResponseStatusCodeSame($expectedResponseCode, 'Invalid response code');
        $this->assertSame($expectedResponse, $this->client->getResponse()->getContent());

        $smsSender = $this->client->getContainer()->get(DebugSmsSender::class);

        if (is_null($expectedSms)) {
            $this->assertCount(0, $smsSender->getSentMessages());
        } else {
            $this->assertCount(1, $smsSender->getSentMessages());
            $translator = $this->client->getContainer()->get(TranslatorInterface::class);
            $rendered = $smsSender->getSentMessages()[0]['message']->trans($translator);
            if (is_string($expectedSms)) {
                $this->assertSame($expectedSms, $rendered);
            } else {
                $this->assertThat($rendered, $expectedSms);
            }
        }

        $db = $this->client->getContainer()->get(DbInterface::class);
        $receivedSms = $db->query('SELECT * FROM received WHERE sms_uuid = ?', [$smsUuid])->fetchAllAssoc();
        $this->assertCount(1, $receivedSms);
        $this->assertSame($phoneNumber, $receivedSms[0]['sender']);
        $this->assertSame($message, $receivedSms[0]['sms_text']);
        $this->assertSame($smsUuid, $receivedSms[0]['sms_uuid']);

        if (!empty($expectedLog)) {
            $this->expectLog(...$expectedLog);
        }
    }

    public static function smsDataProvider(): iterable
    {
        yield 'invalid phone number' => [
            'phoneNumber' => '0000000000000',
            'message' => 'Test message',
            'expectedResponse' => 'Invalid phone number',
            'expectedResponseCode' => Response::HTTP_BAD_REQUEST,
            'expectedSms' => null,
            'expectedLog' => [
                Logger::ERROR, '/Invalid phone number/',
            ],
        ];
        yield 'unknown user' => [
            'phoneNumber' => '421951000000',
            'message' => 'Test message',
            'expectedResponse' => 'User not found',
            'expectedResponseCode' => Response::HTTP_BAD_REQUEST,
            'expectedSms' => null,
            'expectedLog' => [
                Logger::ERROR, '/User not found/',
            ],
        ];
        yield 'invalid message' => [
            'phoneNumber' => self::USER_PHONE_NUMBER,
            'message' => 'Test message',
            'expectedResponse' => '',
            'expectedResponseCode' => Response::HTTP_OK,
            'expectedSms' => null,
            'expectedLog' => [
                Logger::ERROR, '/Error processing SMS/',
            ],
        ];
        yield 'not full command' => [
            'phoneNumber' => self::USER_PHONE_NUMBER,
            'message' => 'RENT',
            'expectedResponse' => '',
            'expectedResponseCode' => Response::HTTP_OK,
            'expectedSms' => 'Error. More arguments needed, use command with bike number: RENT 42',
            'expectedLog' => [],
        ];
        yield 'full command' => [
            'phoneNumber' => self::USER_PHONE_NUMBER,
            'message' => 'HELP',
            'expectedResponse' => '',
            'expectedResponseCode' => Response::HTTP_OK,
            'expectedSms' => new Callback(static function ($message) {
                return (bool)preg_match('/Commands:.*/', $message);
            }),
            'expectedLog' => [],
        ];
        yield 'full command with param' => [
            'phoneNumber' => self::USER_PHONE_NUMBER,
            'message' => 'WHERE 1',
            'expectedResponse' => '',
            'expectedResponseCode' => Response::HTTP_OK,
            'expectedSms' => new Callback(static function ($message) {
                return (bool)preg_match('/Bike 1 is at stand STAND\d*. /', $message);
            }),
            'expectedLog' => [],
        ];
        yield 'invalid privileges' => [
            'phoneNumber' => self::USER_PHONE_NUMBER,
            'message' => 'FORCERENT 1',
            'expectedResponse' => '',
            'expectedResponseCode' => Response::HTTP_OK,
            'expectedSms' => 'Sorry, this command is only available for the privileged users.',
            'expectedLog' => [
                Logger::WARNING, '/Validation error/',
            ],
        ];
    }

    public function testSwitchLanguage(): void
    {
        $user = $this->client->getContainer()->get(UserProvider::class)->loadUserByIdentifier(self::USER_PHONE_NUMBER);
        $this->client->loginUser($user);

        $this->client->request(Request::METHOD_GET, '/switchLanguage/de');
        $this->assertResponseRedirects('/');

        $userSettingsRepository = $this->client->getContainer()->get(UserSettingsRepository::class);
        $userSettings = $userSettingsRepository->findByUserId($user->getUserId());
        $this->assertSame('de', $userSettings['locale']);
        $this->client->request(Request::METHOD_GET, '/logout');

        $this->client->request(
            Request::METHOD_GET,
            '/receive.php',
            [
                'number' => self::USER_PHONE_NUMBER,
                'message' => 'WHERE 1',
                'uuid' => md5((string)microtime(true)),
                'time' => time(),
            ]
        );

        $this->assertResponseIsSuccessful();

        $smsSender = $this->client->getContainer()->get(DebugSmsSender::class);
        $this->assertCount(1, $smsSender->getSentMessages());
        $sent = $smsSender->getSentMessages()[0];
        $this->assertSame('de', $sent['locale']);
        $translator = $this->client->getContainer()->get(TranslatorInterface::class);
        $this->assertMatchesRegularExpression(
            '/Fahrrad 1 befindet sich am Ständer STAND\d*./',
            $sent['message']->trans($translator, $sent['locale'])
        );

        //return language to default value
        $this->client->loginUser($user);
        $this->client->request(Request::METHOD_GET, '/switchLanguage');
    }
}
