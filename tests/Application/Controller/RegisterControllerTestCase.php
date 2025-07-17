<?php

declare(strict_types=1);

namespace BikeShare\Test\Application\Controller;

use BikeShare\Test\Application\BikeSharingWebTestCase;
use Symfony\Component\HttpFoundation\Request;

class RegisterControllerTestCase extends BikeSharingWebTestCase
{
    private string $smsConnector;

    protected function setUp(): void
    {
        parent::setUp();
        $this->smsConnector = $_ENV['SMS_CONNECTOR'] ?? '';
    }

    protected function tearDown(): void
    {
        $_ENV['SMS_CONNECTOR'] = $this->smsConnector;
        parent::tearDown();
    }

    /**
     * @dataProvider provideRegistrationSteps
     */
    public function testRegistrationSteps(
        string $smsConnector,
        int $registrationStep,
        string $expectedTitle
    ): void {
        $_ENV['SMS_CONNECTOR'] = $smsConnector;

        $this->client->getContainer()->get('session')->set('registrationStep', $registrationStep);

        $this->client->catchExceptions(false);

        $this->client->request(Request::METHOD_GET, '/register');

        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('h1', $expectedTitle);
    }

    public function provideRegistrationSteps()
    {
        yield 'smsSystemEnabledStep1' => [
            'smsConnector' => 'euroSms',
            'registrationStep' => 1,
            'expectedTitle' => 'Create account',
        ];
        yield 'smsSystemEnabledStep2' => [
            'smsConnector' => 'euroSms',
            'registrationStep' => 2,
            'expectedTitle' => 'Create account',
        ];
        yield 'smsSystemDisabledStep1' => [
            'smsConnector' => '',
            'registrationStep' => 1,
            'expectedTitle' => 'Create account',
        ];
        yield 'smsSystemDisabledStep2' => [
            'smsConnector' => '',
            'registrationStep' => 2,
            'expectedTitle' => 'Create account',
        ];
    }
}
