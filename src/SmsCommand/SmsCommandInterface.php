<?php

declare(strict_types=1);

namespace BikeShare\SmsCommand;

interface SmsCommandInterface
{
    public static function getName(): string;

    public function getHelpMessage(): string;
}
