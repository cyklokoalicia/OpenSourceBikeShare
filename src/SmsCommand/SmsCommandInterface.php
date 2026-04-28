<?php

declare(strict_types=1);

namespace BikeShare\SmsCommand;

use Symfony\Contracts\Translation\TranslatableInterface;

interface SmsCommandInterface
{
    public static function getName(): string;

    public function getHelpMessage(): TranslatableInterface;
}
