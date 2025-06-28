<?php

declare(strict_types=1);

namespace BikeShare\Test\Application\Controller;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Request;

class RegisterControllerTest extends WebTestCase
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
        $client = static::createClient();
        $client->getContainer()->get('session')->set('registrationStep', $registrationStep);

        $client->catchExceptions(false);

        $client->request(Request::METHOD_GET, '/register');

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
