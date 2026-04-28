<?php

declare(strict_types=1);

namespace BikeShare\Translation;

use Symfony\Contracts\Translation\TranslatableInterface;

interface TranslatableResult extends TranslatableInterface
{
    public function getCode(): string;

    public function getParams(): array;
}
