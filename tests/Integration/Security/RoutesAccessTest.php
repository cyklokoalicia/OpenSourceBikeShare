<?php

declare(strict_types=1);

namespace BikeShare\Test\Integration\Security;

use BikeShare\Test\Integration\BikeSharingKernelTestCase;
use Symfony\Component\HttpFoundation\Request;

class RoutesAccessTest extends BikeSharingKernelTestCase
{
    private const ADMIN_ROUTES = [
        '/api/v1/admin/bikes' => Request::METHOD_GET,
        '/api/v1/admin/bikes/1' => Request::METHOD_GET,
        '/api/v1/admin/bikes/1/last-usage' => Request::METHOD_GET,
        '/api/v1/admin/coupons' => Request::METHOD_GET,
        '/api/v1/admin/coupons/ABC123/sell' => Request::METHOD_POST,
        '/api/v1/admin/coupons/generate' => Request::METHOD_POST,
        '/api/v1/admin/users/1/credit' => Request::METHOD_PUT,
        '/api/v1/admin/reports/daily' => Request::METHOD_GET,
        '/api/v1/admin/reports/inactive-bikes' => Request::METHOD_GET,
        '/api/v1/admin/reports/users/2025' => Request::METHOD_GET,
        '/api/v1/admin/stands' => Request::METHOD_GET,
        '/api/v1/admin/users' => Request::METHOD_GET,
        '/api/v1/admin/users/1' => Request::METHOD_GET,
    ];

    private const PUBLIC_ROUTES = [
        '/api/v1/auth/token' => [Request::METHOD_POST],
        '/api/v1/auth/refresh' => [Request::METHOD_POST],
        '/api/v1/auth/logout' => [Request::METHOD_POST],
        '/login' => [],
        '/sms/receive.php' => [],
        '/receive.php' => [],
        '/register.php' => [],
        '/register' => [],
        '/user/confirm/email/' => [],
        '/resetPassword' => [],
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
