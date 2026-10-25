<?php

declare(strict_types=1);

namespace BikeShare\Test\Application\Controller;

use BikeShare\App\Security\UserProvider;
use BikeShare\Test\Application\BikeSharingWebTestCase;
use Monolog\Logger;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class QrCodeGeneratorControllerTest extends BikeSharingWebTestCase
{
    private const SUPER_ADMIN_PHONE_NUMBER = '421951777777';
    private const ADMIN_PHONE_NUMBER = '421951222222';
    private const USER_PHONE_NUMBER = '421951111111';

    public function testSuperAdminDownloadsQrCodesPdf(): void
    {
        $superAdmin = $this->client->getContainer()->get(UserProvider::class)
            ->loadUserByIdentifier(self::SUPER_ADMIN_PHONE_NUMBER);
        $this->client->loginUser($superAdmin);

        $this->client->request(Request::METHOD_GET, '/admin/qrCodeGenerator');

        $this->assertResponseIsSuccessful();
        $this->assertResponseHeaderSame('Content-Type', 'application/pdf');
        $this->assertResponseHeaderSame('Content-Disposition', 'attachment; filename="qrcodes.pdf"');

        $content = $this->client->getInternalResponse()->getContent();
        $this->assertStringStartsWith('%PDF-', $content);
        $this->assertStringContainsString('%%EOF', $content);
    }

    #[DataProvider('nonSuperAdminProvider')]
    public function testNonSuperAdminIsDenied(string $phoneNumber): void
    {
        $user = $this->client->getContainer()->get(UserProvider::class)
            ->loadUserByIdentifier($phoneNumber);
        $this->client->loginUser($user);
        $this->expectLog(Logger::ERROR, '/Access Denied/');

        $this->client->request(Request::METHOD_GET, '/admin/qrCodeGenerator');

        $this->assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);
        $this->assertResponseHeaderNotSame('Content-Type', 'application/pdf');
    }

    public static function nonSuperAdminProvider(): iterable
    {
        yield 'user' => [self::USER_PHONE_NUMBER];
        yield 'admin' => [self::ADMIN_PHONE_NUMBER];
    }
}
