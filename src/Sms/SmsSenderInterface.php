<?php

namespace BikeShare\Sms;

use Symfony\Contracts\Translation\TranslatableInterface;

interface SmsSenderInterface
{
    public function send(string $number, TranslatableInterface $message, ?string $locale = null): void;
}
