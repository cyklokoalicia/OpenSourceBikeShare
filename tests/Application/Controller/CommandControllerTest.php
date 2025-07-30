<?php

declare(strict_types=1);

namespace BikeShare\Test\Application\Controller;

use BikeShare\Test\Application\BikeSharingWebTestCase;
use Symfony\Component\HttpFoundation\Request;

class CommandControllerTest extends BikeSharingWebTestCase
{
    public function testCommand(): void
    {
        $this->client->request(
            Request::METHOD_GET,
            '/command.php',
            ['action' => 'map:markers']
        );
        $this->assertResponseIsSuccessful();
        $response = $this->client->getResponse()->getContent();
        $this->assertJson($response, 'Response is not JSON');
        $response = json_decode($response, true, 512, JSON_THROW_ON_ERROR);
        foreach ($response as $marker) {
            $this->assertArrayHasKey('standId', $marker, 'Marker does not contain standId');
            $this->assertArrayHasKey('bikeCount', $marker, 'Marker does not contain bikeCount');
            $this->assertArrayHasKey('standName', $marker, 'Marker does not contain standName');
            $this->assertArrayHasKey('standDescription', $marker, 'Marker does not contain standDescription');
            $this->assertArrayHasKey('standPhoto', $marker, 'Marker does not contain standPhoto');
            $this->assertArrayHasKey('longitude', $marker, 'Marker does not contain longitude');
            $this->assertArrayHasKey('latitude', $marker, 'Marker does not contain latitude');
        }
    }
}
