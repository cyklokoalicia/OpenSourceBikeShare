<?php

declare(strict_types=1);

namespace BikeShare\Rent\BikeCodeGenerator;

interface BikeCodeGeneratorInterface
{
    /**
     * @return string 4-digit zero-padded unlock code.
     */
    public function generate(): string;
}
