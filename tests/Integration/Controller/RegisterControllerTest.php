<?php

declare(strict_types=1);

namespace Test\BikeShare\Integration\Controller;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Request;

class RegisterControllerTest extends WebTestCase
{
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

        $crawler = $client->request(Request::METHOD_GET, '/register');

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
