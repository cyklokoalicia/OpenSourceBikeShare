<?php

declare(strict_types=1);

namespace BikeShare\Rent\DTO;

use BikeShare\Rent\Enum\RentSystemType;
use BikeShare\Translation\TranslatableResult;
use Symfony\Component\Translation\TranslatableMessage;
use Symfony\Contracts\Translation\TranslatorInterface;

class RentSystemResult implements TranslatableResult, \JsonSerializable
{
    public function __construct(
        private readonly bool $error,
        private readonly string $code,
        private readonly RentSystemType $systemType,
        private readonly array $params = [],
    ) {
        if (trim($this->code) === '') {
            throw new \InvalidArgumentException('code cannot be empty.');
        }
    }

    public function trans(TranslatorInterface $translator, ?string $locale = null): string
    {
        // Delegate to TranslatableMessage so nested TranslatableInterface params get rendered recursively.
        return (new TranslatableMessage($this->code, $this->params))->trans($translator, $locale);
    }

    public function isError(): bool
    {
        return $this->error;
    }

    public function getCode(): string
    {
        return $this->code;
    }

    public function getParams(): array
    {
        return $this->params;
    }

    public function getSystemType(): RentSystemType
    {
        return $this->systemType;
    }

    public function jsonSerialize(): array
    {
        return [
            'error' => $this->error,
            'code' => $this->code,
            'params' => (object)$this->params,
        ];
    }
}
