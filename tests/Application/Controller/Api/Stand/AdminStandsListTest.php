<?php

declare(strict_types=1);

namespace BikeShare\Test\Application\Controller\Api\Stand;

use BikeShare\App\Security\UserProvider;
use BikeShare\Test\Application\BikeSharingWebTestCase;
use Symfony\Component\HttpFoundation\Request;

class AdminStandsListTest extends BikeSharingWebTestCase
{
    private const ADMIN_PHONE_NUMBER = '421951222222';

    private string $originalCities;

    protected function setUp(): void
    {
        $this->originalCities = $_ENV['CITIES'] ?? '';
        parent::setUp();
    }

    protected function tearDown(): void
    {
        $_ENV['CITIES'] = $this->originalCities;
        parent::tearDown();
    }

    public function testSingleCityListIncludesCityAndExcludesNonConfigured(): void
    {
        $this->loginAdmin();

        $this->client->request(Request::METHOD_GET, '/api/v1/admin/stands');
        $this->assertResponseIsSuccessful();

        $names = $this->collectStandNamesAndAssertCity();

        $this->assertContains('STAND1', $names);
        $this->assertNotContains(
            'ORPHAN_STAND',
            $names,
            'PhantomCity stand must be hidden in single-city deployment',
        );
        $this->assertNotContains(
            'OTHER_CITY_STAND',
            $names,
            "Stand in 'Other City' must be hidden when only Default City is configured",
        );
    }

    public function testMultiCityListIncludesEveryConfiguredCity(): void
    {
        $_ENV['CITIES'] = json_encode([
            'Default City' => [48.148154, 17.117232],
            'Other City' => [50.0, 20.0],
        ]);
        $this->loginAdmin();

        $this->client->request(Request::METHOD_GET, '/api/v1/admin/stands');
        $this->assertResponseIsSuccessful();

        $names = $this->collectStandNamesAndAssertCity();

        $this->assertContains('STAND1', $names);
        $this->assertContains(
            'OTHER_CITY_STAND',
            $names,
            "Stand in 'Other City' must appear once 'Other City' is added to CITIES",
        );
        $this->assertNotContains(
            'ORPHAN_STAND',
            $names,
            'PhantomCity stand must stay hidden — not in CITIES',
        );
    }

    public function testEmptyCitiesConfigYieldsEmptyList(): void
    {
        $_ENV['CITIES'] = '{}';
        $this->loginAdmin();

        $this->client->request(Request::METHOD_GET, '/api/v1/admin/stands');
        $this->assertResponseIsSuccessful();

        $stands = $this->decodeApiResponseData();
        $this->assertSame(
            [],
            $stands,
            'When no cities are configured, the admin list must be empty',
        );
    }

    private function loginAdmin(): void
    {
        $admin = $this->client->getContainer()->get(UserProvider::class)
            ->loadUserByIdentifier(self::ADMIN_PHONE_NUMBER);
        $this->client->loginUser($admin);
    }

    /**
     * @return list<string> stand names from the response, with `city` field assertions applied.
     */
    private function collectStandNamesAndAssertCity(): array
    {
        $stands = $this->decodeApiResponseData();
        $this->assertNotEmpty($stands);

        $names = [];
        foreach ($stands as $stand) {
            $this->assertArrayHasKey('city', $stand, 'Admin stand list must expose city');
            $this->assertNotEmpty($stand['city']);
            $names[] = $stand['standName'];
        }

        return $names;
    }
}
