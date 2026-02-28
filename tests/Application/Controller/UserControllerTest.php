<?php

declare(strict_types=1);

namespace BikeShare\Test\Application\Controller;

use BikeShare\Db\DbInterface;
use BikeShare\Repository\UserRepository;
use BikeShare\Test\Application\BikeSharingWebTestCase;

class UserControllerTest extends BikeSharingWebTestCase
{
    private const USER_PHONE = '421951555555';

    protected function setUp(): void
    {
        parent::setUp();
        $userRepository = $this->client->getContainer()->get(UserRepository::class);
        $user = $userRepository->findItemByPhoneNumber(self::USER_PHONE);
        if ($user !== null) {
            $db = $this->client->getContainer()->get(DbInterface::class);
            $db->query(
                'UPDATE users SET isNumberConfirmed = 1 WHERE userId = :userId',
                ['userId' => $user['userId']]
            );
        }
    }

    private function logIn(string $username, string $password)
    {
        $this->client->request('GET', '/login');
        $this->client->submitForm('Login', [
            'number' => $username,
            'password' => $password,
        ]);
        $this->client->followRedirect();
    }

    public function testUserProfilePageIsSecure(): void
    {
        $this->client->request('GET', '/user/profile');
        $this->assertResponseRedirects('/login');
    }

    public function testUserProfilePage(): void
    {
        $this->logIn(self::USER_PHONE, 'password');
        $this->client->request('GET', '/user/profile');
        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('h1', 'User Profile');
    }

    public function testChangePassword(): void
    {
        $this->logIn(self::USER_PHONE, 'password');
        $crawler = $this->client->request('GET', '/user/profile');

        $form = $crawler->selectButton('Change Password')->form([
            'change_password_form[currentPassword]' => 'password',
            'change_password_form[plainPassword][first]' => 'new-password',
            'change_password_form[plainPassword][second]' => 'new-password',
        ]);
        $this->client->submit($form);

        $this->assertResponseRedirects('/user/profile');
        $this->client->followRedirect();
        $this->assertSelectorExists('.alert-success');

        // Logout and login with new password
        $this->client->request('GET', '/logout');
        $this->client->followRedirect();
        $this->logIn(self::USER_PHONE, 'new-password');
        $this->assertResponseIsSuccessful();
    }
}
