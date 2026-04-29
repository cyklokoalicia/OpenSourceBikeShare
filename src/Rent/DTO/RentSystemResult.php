<?php

declare(strict_types=1);

namespace BikeShare\Rent\DTO;

use BikeShare\Rent\Enum\RentSystemType;
use BikeShare\Translation\TranslatableResult;
use Symfony\Component\Translation\TranslatableMessage;
use Symfony\Contracts\Translation\TranslatableInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

class RentSystemResult implements TranslatableResult, \JsonSerializable
{
    private const TRANSLATION_DOMAIN = 'rentSystem';

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
        // Scalar params are htmlspecialchars-escaped unconditionally so that HTML consumers
        // (api console via .html(), scan/* templates via |raw) are safe even on the error path.
        // Channel is auto-injected from systemType so the `rentSystem` domain can branch
        // wording per transport (sms = plain text, other = HTML with badge spans).
        // nl2br only on non-SMS — SMS is plain text and shouldn't get <br /> markup.
        $params = $this->params + ['channel' => $this->systemType->value];
        foreach ($params as $key => $value) {
            if ($value instanceof TranslatableInterface) {
                $value = $value->trans($translator, $locale);
            }
            if (is_scalar($value)) {
                $value = htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
            }
            $params[$key] = $value;
        }
        $rendered = (new TranslatableMessage($this->code, $params, self::TRANSLATION_DOMAIN))
            ->trans($translator, $locale);

        return $this->systemType === RentSystemType::SMS ? $rendered : nl2br($rendered);
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
