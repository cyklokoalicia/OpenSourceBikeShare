<?php

declare(strict_types=1);

namespace BikeShare\Test\Application\Controller;

use BikeShare\Db\DbInterface;
use BikeShare\Repository\UserRepository;
use BikeShare\Test\Application\BikeSharingWebTestCase;

class UserControllerTest extends BikeSharingWebTestCase
{
    private const USER_PHONE = '421951555555';
    private const REMEMBER_ME_COOKIE = 'REMEMBERME';

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

    protected function tearDown(): void
    {
        $userRepository = $this->client->getContainer()->get(UserRepository::class);
        $user = $userRepository->findItemByPhoneNumber(self::USER_PHONE);
        if ($user !== null) {
            $db = $this->client->getContainer()->get(DbInterface::class);
            $db->query(
                'UPDATE users SET password = :password WHERE userId = :userId',
                [
                    'userId' => $user['userId'],
                    'password' => password_hash('password', PASSWORD_BCRYPT, ['cost' => 13]),
                ]
            );
        }
        parent::tearDown();
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

    public function testChangePasswordReissuesRememberMeCookie(): void
    {
        $this->logIn(self::USER_PHONE, 'password');
        $oldCookie = $this->client->getCookieJar()->get(self::REMEMBER_ME_COOKIE);
        $this->assertNotNull($oldCookie);

        $this->changePassword('password', 'new-password');

        $newCookie = $this->client->getCookieJar()->get(self::REMEMBER_ME_COOKIE);
        $this->assertNotNull($newCookie);
        $this->assertNotSame($oldCookie->getValue(), $newCookie->getValue());

        // Session gone, only the reissued remember-me cookie left: still signed in.
        $this->client->getCookieJar()->clear();
        $this->client->getCookieJar()->set($newCookie);
        $this->client->request('GET', '/user/profile');
        $this->assertResponseIsSuccessful();
    }

    public function testRememberMeCookieIssuedBeforePasswordChangeIsRejected(): void
    {
        $this->logIn(self::USER_PHONE, 'password');
        $oldCookie = $this->client->getCookieJar()->get(self::REMEMBER_ME_COOKIE);
        $this->assertNotNull($oldCookie);

        $this->changePassword('password', 'new-password');

        // Another device that still holds the old cookie must log in again.
        $this->client->getCookieJar()->clear();
        $this->client->getCookieJar()->set($oldCookie);
        $this->client->request('GET', '/user/profile');
        $this->assertResponseRedirects('/login');
    }

    private function changePassword(string $currentPassword, string $newPassword): void
    {
        $crawler = $this->client->request('GET', '/user/profile');
        $this->client->submit($crawler->selectButton('Change Password')->form([
            'change_password_form[currentPassword]' => $currentPassword,
            'change_password_form[plainPassword][first]' => $newPassword,
            'change_password_form[plainPassword][second]' => $newPassword,
        ]));
        $this->assertResponseRedirects('/user/profile');
    }
}
