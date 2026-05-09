<?php

declare(strict_types=1);

namespace BikeShare\Controller;

use BikeShare\Gbfs\GbfsFeedBuilder;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class GbfsController extends AbstractController
{
    public function __construct(
        private readonly bool $isGbfsEnabled,
        private readonly GbfsFeedBuilder $feedBuilder,
        /** @var list<string> */
        private readonly array $enabledLocales,
    ) {
    }

    public function manifest(): JsonResponse
    {
        $this->guardEnabled();

        return new JsonResponse($this->feedBuilder->buildManifest());
    }

    public function systemInformation(string $locale): JsonResponse
    {
        $this->guard($locale);

        return new JsonResponse($this->feedBuilder->buildSystemInformation($locale));
    }

    public function stationInformation(string $locale): JsonResponse
    {
        $this->guard($locale);

        return new JsonResponse($this->feedBuilder->buildStationInformation());
    }

    public function stationStatus(string $locale): JsonResponse
    {
        $this->guard($locale);

        return new JsonResponse($this->feedBuilder->buildStationStatus());
    }

    public function vehicleTypes(string $locale): JsonResponse
    {
        $this->guard($locale);

        return new JsonResponse($this->feedBuilder->buildVehicleTypes());
    }

    private function guard(string $locale): void
    {
        $this->guardEnabled();

        if (!in_array($locale, $this->enabledLocales, true)) {
            throw new NotFoundHttpException();
        }
    }

    private function guardEnabled(): void
    {
        if (!$this->isGbfsEnabled) {
            throw new NotFoundHttpException();
        }
    }
}
