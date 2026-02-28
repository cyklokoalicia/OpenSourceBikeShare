<?php

declare(strict_types=1);

namespace BikeShare\Test\Application\Controller\Api;

use BikeShare\Test\Application\BikeSharingWebTestCase;

class ApiAuthenticationTest extends BikeSharingWebTestCase
{
    public function testApiAccessWithoutToken(): void
    {
        $this->client->request('GET', '/api/v1/admin/stands');
        $this->assertResponseStatusCodeSame(401);

        $payload = $this->decodeJsonResponse();
        $this->assertArrayHasKey('detail', $payload);
        $this->assertArrayNotHasKey('message', $payload);
    }
}
