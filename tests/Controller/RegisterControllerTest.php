<?php

declare(strict_types=1);

namespace Test\BikeShare\Controller;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

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

        $crawler = $client->request('GET', '/register');

        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('h1', $expectedTitle);
    }

    public function provideRegistrationSteps()
    {
        yield 'smsSystemEnabledStep1' => [
            'smsConnector' => 'euroSms',
            'registrationStep' => 1,
            'expectedTitle' => 'Step 1 - Confirm your phone number',
        ];
        yield 'smsSystemEnabledStep2' => [
            'smsConnector' => 'euroSms',
            'registrationStep' => 2,
            'expectedTitle' => 'Step 2 - Create account',
        ];
        yield 'smsSystemDisabledStep1' => [
            'smsConnector' => '',
            'registrationStep' => 1,
            'expectedTitle' => 'Step 1 - Create account',
        ];
        yield 'smsSystemDisabledStep2' => [
            'smsConnector' => '',
            'registrationStep' => 2,
            'expectedTitle' => 'Step 1 - Create account',
        ];
    }
}
