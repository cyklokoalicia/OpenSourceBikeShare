<?php

declare(strict_types=1);

namespace BikeShare\App\Api\Compat;

interface ApiResponseTransformInterface
{
    /**
     * The app version that introduced this breaking change (semver).
     * Clients with version < this will receive the transformed (old) response.
     */
    public function getSinceVersion(): string;

    /**
     * Symfony route names this transform applies to.
     * Return empty array to apply to all API routes.
     *
     * @return string[]
     */
    public function getRoutes(): array;

    /**
     * Transform new-format response data to old-format for legacy clients.
     */
    public function transform(array $data): array;
}
