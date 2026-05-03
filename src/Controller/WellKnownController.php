<?php

declare(strict_types=1);

namespace BikeShare\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Serves files under the /.well-known/ namespace (RFC 8615). New endpoints (e.g.
 * apple-app-site-association, security.txt, change-password) should be added here
 * as additional action methods + matching routes.
 */
class WellKnownController extends AbstractController
{
    private const FINGERPRINT_REGEX = '/^(?:[0-9A-F]{2}:){31}[0-9A-F]{2}$/';

    /**
     * Digital Asset Links statement consumed by Android to verify App Links for the
     * BikeShare app — so scanning a /scan.php/... QR with the system camera opens
     * the app directly without a chooser sheet.
     *
     * https://developers.google.com/digital-asset-links/v1/getting-started
     */
    public function assetLinks(string $packageName, string $fingerprintsCsv): JsonResponse
    {
        $fingerprints = $this->parseFingerprints($fingerprintsCsv);
        if ($fingerprints === [] || $packageName === '') {
            throw new NotFoundHttpException('Android App Links are not configured for this deployment');
        }

        return new JsonResponse([[
            'relation' => ['delegate_permission/common.handle_all_urls'],
            'target' => [
                'namespace' => 'android_app',
                'package_name' => $packageName,
                'sha256_cert_fingerprints' => $fingerprints,
            ],
        ]]);
    }

    /**
     * @return list<string>
     */
    private function parseFingerprints(string $csv): array
    {
        $valid = [];
        foreach (explode(',', $csv) as $item) {
            $normalized = strtoupper(trim($item));
            if ($normalized !== '' && preg_match(self::FINGERPRINT_REGEX, $normalized) === 1) {
                $valid[] = $normalized;
            }
        }
        return $valid;
    }
}
