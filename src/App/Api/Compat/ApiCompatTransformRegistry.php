<?php

declare(strict_types=1);

namespace BikeShare\App\Api\Compat;

class ApiCompatTransformRegistry
{
    /** @var ApiResponseTransformInterface[] */
    private array $transforms;

    /**
     * @param iterable<ApiResponseTransformInterface> $transforms
     */
    public function __construct(iterable $transforms)
    {
        $this->transforms = $transforms instanceof \Traversable
            ? iterator_to_array($transforms)
            : (array) $transforms;

        // Sort newest first so transforms compose correctly (latest change reversed first)
        usort($this->transforms, static function (
            ApiResponseTransformInterface $a,
            ApiResponseTransformInterface $b
        ): int {
            return version_compare($b->getSinceVersion(), $a->getSinceVersion());
        });
    }

    /**
     * @return ApiResponseTransformInterface[]
     */
    public function getTransformsFor(string $clientVersion, string $routeName): array
    {
        $applicable = [];

        foreach ($this->transforms as $transform) {
            if (version_compare($clientVersion, $transform->getSinceVersion(), '>=')) {
                continue;
            }

            $routes = $transform->getRoutes();
            if (!empty($routes) && !in_array($routeName, $routes, true)) {
                continue;
            }

            $applicable[] = $transform;
        }

        return $applicable;
    }
}
