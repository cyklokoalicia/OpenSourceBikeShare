<?php

declare(strict_types=1);

namespace BikeShare\Sentry;

use Sentry\Tracing\SamplingContext;
use Symfony\Component\HttpFoundation\RequestStack;

readonly class GbfsTracesSampler
{
    public function __construct(private RequestStack $requestStack)
    {
    }

    public function __invoke(SamplingContext $context): float
    {
        $request = $this->requestStack->getMainRequest();
        if ($request !== null && str_starts_with($request->getPathInfo(), '/gbfs')) {
            return 0.01;
        }

        return 1.0;
    }
}
