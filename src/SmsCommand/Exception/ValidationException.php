<?php

declare(strict_types=1);

namespace BikeShare\SmsCommand\Exception;

class ValidationException extends \RuntimeException
{
    public function __construct(
        string $code,
        private readonly array $parameters = [],
        ?\Throwable $previous = null,
    ) {
        // parent::getMessage() exposes the translation key as a stable sentinel for logs/Sentry.
        parent::__construct($code, 0, $previous);
    }

    public function getParameters(): array
    {
        return $this->parameters;
    }
}
