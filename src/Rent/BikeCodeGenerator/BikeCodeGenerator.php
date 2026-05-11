<?php

declare(strict_types=1);

namespace BikeShare\Rent\BikeCodeGenerator;

class BikeCodeGenerator implements BikeCodeGeneratorInterface
{
    public function generate(): string
    {
        // Range chosen to avoid codes that look broken on a 4-digit display:
        // 0000–0099 (multiple leading zeros) and 9901–9999 (almost-all-nines).
        return sprintf('%04d', random_int(100, 9900));
    }
}
