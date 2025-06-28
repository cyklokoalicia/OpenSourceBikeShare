<?php

declare(strict_types=1);

namespace BikeShare\Test\Application;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

abstract class BikeSharingWebTestCaseTest extends WebTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
    }

    protected function tearDown(): void
    {
        // Cleanup can be done here if needed
        parent::tearDown();
    }
}
