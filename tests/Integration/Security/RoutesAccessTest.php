<?php

declare(strict_types=1);

namespace BikeShare\Test\Integration\Security;

use BikeShare\Test\Integration\BikeSharingKernelTestCase;
use Symfony\Component\HttpFoundation\Request;

class RoutesAccessTest extends BikeSharingKernelTestCase
{
    private const ADMIN_ROUTES = [
        '/api/bike' => 'GET',
        '/api/bike/1' => 'GET',
        '/api/bikeLastUsage/1' => 'GET',
        '/api/coupon' => 'GET',
        '/api/coupon/sell/1' => 'POST',
        '/api/coupon/generate' => 'POST',
        '/api/credit' => 'PUT',
        '/api/report/daily' => 'GET',
        '/api/report/user/2025' => 'GET',
        '/api/stand' => 'GET',
        '/api/user' => 'GET',
        '/api/user/1' => 'GET',
    ];

    private const PUBLIC_ROUTES = [
        '/login' => [],
        '/sms/receive.php' => [],
        '/receive.php' => [],
        '/register.php' => [],
        '/register' => [],
        '/user/confirm/email/' => [],
        '/resetPassword' => [],
        '/command.php' => [],
    ];

    private $creditSystemEnabled;

    protected function setUp(): void
    {
        $this->creditSystemEnabled = $_ENV['CREDIT_SYSTEM_ENABLED'];
        $_ENV['CREDIT_SYSTEM_ENABLED'] = '1';
        parent::setUp();
    }

    protected function tearDown(): void
    {
        $_ENV['CREDIT_SYSTEM_ENABLED'] = $this->creditSystemEnabled;
        parent::tearDown();
    }

    /**
     * Test that public routes are accessible
     */
    public function testPublicRoutes(): void
    {
        $accessMap = static::getContainer()->get('security.access_map');
        foreach (self::PUBLIC_ROUTES as $route => $methods) {
            if (empty($methods)) {
                $methods = [Request::METHOD_GET];
            }
            foreach ($methods as $method) {
                $request = Request::create($route, $method);
                [$attributes, $channel] = $accessMap->getPatterns($request);

                $this->assertContains(
                    'PUBLIC_ACCESS',
                    $attributes,
                    'Access map should contain PUBLIC_ACCESS for route ' . $route
                );
            }
        }
    }
}
