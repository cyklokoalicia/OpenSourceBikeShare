<?php

declare(strict_types=1);

namespace BikeShare\Test\Application\Controller;

use BikeShare\Test\Application\BikeSharingWebTestCase;

class SecurityControllerTest extends BikeSharingWebTestCase
{
    /**
     * @see https://github.com/symfony/symfony/issues/27961
     */
    public function testBotLoginWithInvalidCredentials(): void
    {
        $this->client->request(
            'POST',
            '/login',
            [
                'log' => '908131403',
                'pwd' => 'Hlavolam1',
                'redirect_to' => '/wp-admin/&wp-submit=Log In'
            ]
        );

        $this->assertResponseRedirects('/login');
    }
}
