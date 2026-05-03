<?php

declare(strict_types=1);

namespace BikeShare\Test\Application\Controller;

use BikeShare\Test\Application\BikeSharingWebTestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class WellKnownControllerTest extends BikeSharingWebTestCase
{
    public function testAssetLinksReachableWithoutAuthentication(): void
    {
        // The Digital Asset Links verifier (Google) fetches this URL anonymously
        // — it must be reachable without any session/JWT/cookie.
        $this->client->request(Request::METHOD_GET, '/.well-known/assetlinks.json');
        $this->assertResponseIsSuccessful();
    }

    public function testAssetLinksReturnsApplicationJsonContentType(): void
    {
        $this->client->request(Request::METHOD_GET, '/.well-known/assetlinks.json');
        $contentType = $this->client->getResponse()->headers->get('Content-Type') ?? '';
        $this->assertStringStartsWith('application/json', $contentType);
    }

    public function testAssetLinksMatchesDigitalAssetLinksSchema(): void
    {
        $this->client->request(Request::METHOD_GET, '/.well-known/assetlinks.json');
        $body = json_decode($this->client->getResponse()->getContent(), true, flags: JSON_THROW_ON_ERROR);

        $this->assertIsArray($body);
        $this->assertCount(1, $body, 'Endpoint should return a single statement object wrapped in an array');

        $statement = $body[0];
        $this->assertSame(['delegate_permission/common.handle_all_urls'], $statement['relation']);
        $this->assertSame('android_app', $statement['target']['namespace']);
        $this->assertSame('com.bikeshare.app.test', $statement['target']['package_name']);
        $this->assertSame(
            [
                'AB:CD:EF:01:23:45:67:89:AB:CD:EF:01:23:45:67:89:AB:CD:EF:01:23:45:67:89:AB:CD:EF:01:23:45:67:89',
                '11:22:33:44:55:66:77:88:99:00:11:22:33:44:55:66:77:88:99:00:11:22:33:44:55:66:77:88:99:00:11:22',
            ],
            $statement['target']['sha256_cert_fingerprints']
        );
    }

    public function testAssetLinksReturnsNotFoundWhenNoFingerprintsConfigured(): void
    {
        $previous = $_ENV['ANDROID_APP_LINKS_FINGERPRINTS'] ?? null;
        $_ENV['ANDROID_APP_LINKS_FINGERPRINTS'] = '';
        try {
            self::ensureKernelShutdown();
            $this->client = self::createClient();
            $this->client->request(Request::METHOD_GET, '/.well-known/assetlinks.json');
            $this->assertSame(Response::HTTP_NOT_FOUND, $this->client->getResponse()->getStatusCode());
        } finally {
            if ($previous === null) {
                unset($_ENV['ANDROID_APP_LINKS_FINGERPRINTS']);
            } else {
                $_ENV['ANDROID_APP_LINKS_FINGERPRINTS'] = $previous;
            }
        }
    }

    public function testAssetLinksIgnoresMalformedFingerprintsButKeepsValidOnes(): void
    {
        $previous = $_ENV['ANDROID_APP_LINKS_FINGERPRINTS'] ?? null;
        $valid = 'AA:BB:CC:DD:EE:FF:00:11:22:33:44:55:66:77:88:99:AA:BB:CC:DD:EE:FF:00:11:22:33:44:55:66:77:88:99';
        $_ENV['ANDROID_APP_LINKS_FINGERPRINTS'] = "garbage, $valid, 11:22, ZZ:invalid";
        try {
            self::ensureKernelShutdown();
            $this->client = self::createClient();
            $this->client->request(Request::METHOD_GET, '/.well-known/assetlinks.json');
            $this->assertResponseIsSuccessful();
            $body = json_decode($this->client->getResponse()->getContent(), true, flags: JSON_THROW_ON_ERROR);
            $this->assertSame([$valid], $body[0]['target']['sha256_cert_fingerprints']);
        } finally {
            if ($previous === null) {
                unset($_ENV['ANDROID_APP_LINKS_FINGERPRINTS']);
            } else {
                $_ENV['ANDROID_APP_LINKS_FINGERPRINTS'] = $previous;
            }
        }
    }
}
