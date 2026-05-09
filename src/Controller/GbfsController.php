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
    ) {
    }

    public function manifest(): JsonResponse
    {
        $this->guardEnabled();

        return new JsonResponse($this->feedBuilder->buildManifest());
    }

    public function systemInformation(string $locale): JsonResponse
    {
        $this->guardEnabled();

        return new JsonResponse($this->feedBuilder->buildSystemInformation($locale));
    }

    public function stationInformation(): JsonResponse
    {
        $this->guardEnabled();

        return new JsonResponse($this->feedBuilder->buildStationInformation());
    }

    public function stationStatus(): JsonResponse
    {
        $this->guardEnabled();

        return new JsonResponse($this->feedBuilder->buildStationStatus());
    }

    public function vehicleTypes(): JsonResponse
    {
        $this->guardEnabled();

        return new JsonResponse($this->feedBuilder->buildVehicleTypes());
    }

    private function guardEnabled(): void
    {
        if (!$this->isGbfsEnabled) {
            throw new NotFoundHttpException();
        }
    }
}
