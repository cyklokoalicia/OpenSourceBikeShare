<?php

declare(strict_types=1);

namespace BikeShare\Translation;

use Symfony\Contracts\Translation\TranslatableInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Text that is already final (e.g. composed from several translated pieces) and must not be translated again.
 */
class PlainText implements TranslatableInterface
{
    public function __construct(private readonly string $text)
    {
    }

    public function trans(TranslatorInterface $translator, ?string $locale = null): string
    {
        return $this->text;
    }
}
