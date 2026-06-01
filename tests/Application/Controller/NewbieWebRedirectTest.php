<?php

/**
 * Functional tests for WebAccessDeniedHandler (spec 0001): a logged-in newbie
 * (phone not yet confirmed, SMS enabled) is redirected to the phone-confirmation
 * page on the web firewall instead of seeing a bare 403, and can reach that page
 * (no redirect loop).
 */

declare(strict_types=1);

namespace BikeShare\Test\Application\Controller;

use BikeShare\App\Security\UserProvider;
use BikeShare\Test\Application\BikeSharingWebTestCase;
use Symfony\Component\HttpFoundation\Request;

class NewbieWebRedirectTest extends BikeSharingWebTestCase
{
    // userForApiPhoneConfirmTest — isNumberConfirmed: 0
    private const NEWBIE_NUMBER = '421951666666';

    public function testNewbieIsRedirectedToPhoneConfirmPage(): void
    {
        $user = $this->client->getContainer()->get(UserProvider::class)
            ->loadUserByIdentifier(self::NEWBIE_NUMBER);
        self::assertSame(['ROLE_NEWBIE'], $user->getRoles());
        $this->client->loginUser($user);

        $this->client->request(Request::METHOD_GET, '/');

        $this->assertResponseRedirects();
        self::assertStringContainsString(
            '/user/confirm/phone',
            (string)$this->client->getResponse()->headers->get('Location')
        );
    }

    public function testNewbieCanReachPhoneConfirmPage(): void
    {
        $user = $this->client->getContainer()->get(UserProvider::class)
            ->loadUserByIdentifier(self::NEWBIE_NUMBER);
        $this->client->loginUser($user);

        $url = $this->client->getContainer()->get('router')->generate('user_confirm_phone');
        $this->client->request(Request::METHOD_GET, $url);

        $this->assertResponseIsSuccessful();
    }
}
