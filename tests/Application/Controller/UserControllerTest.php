<?php

declare(strict_types=1);

namespace BikeShare\Test\Application\Controller;

use BikeShare\Test\Application\BikeSharingWebTestCase;

class UserControllerTest extends BikeSharingWebTestCase
{
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
        $this->logIn('421951555555', 'password');
        $this->client->request('GET', '/user/profile');
        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('h1', 'User Profile');
    }

    public function testChangePassword(): void
    {
        $this->logIn('421951555555', 'password');
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
        $this->logIn('421951555555', 'new-password');
        $this->assertResponseIsSuccessful();
    }
}
