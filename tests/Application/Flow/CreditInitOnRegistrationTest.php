<?php

declare(strict_types=1);

namespace BikeShare\Test\Application\Flow;

use BikeShare\Repository\UserRepository;
use BikeShare\Test\Application\BikeSharingWebTestCase;
use Symfony\Component\HttpFoundation\Request;

class CreditInitOnRegistrationTest extends BikeSharingWebTestCase
{
    public function testNewUserCreditIsZeroAfterRegistration(): void
    {
        $userEmail = 'credit_init_test_' . uniqid() . '@example.com';
        $userPhone = '+421901' . rand(100000, 999999);

        $this->client->request(Request::METHOD_GET, '/register');
        $this->assertResponseIsSuccessful();

        $this->client->submitForm('register', [
            'registration_form[fullname]' => 'Jane Doe',
            'registration_form[city]' => 'Default City',
            'registration_form[useremail]' => $userEmail,
            'registration_form[password]' => 'password123',
            'registration_form[password2]' => 'password123',
            'registration_form[number]' => $userPhone,
            'registration_form[agree]' => '1',
        ]);
        $this->assertResponseRedirects('/');

        $user = static::getContainer()->get(UserRepository::class)->findItemByEmail($userEmail);
        $this->assertNotNull($user);
        $this->assertSame(0.0, (float) $user['credit']);
    }
}
