<?php

declare(strict_types=1);

namespace BikeShare\Test\Application\Controller\Api;

use BikeShare\Test\Application\BikeSharingWebTestCase;

class ApiAuthenticationTest extends BikeSharingWebTestCase
{
    public function testApiAccessWithoutToken(): void
    {
        $this->client->request('GET', '/api/stand');
        $this->assertEquals(401, $this->client->getResponse()->getStatusCode());
    }
}
