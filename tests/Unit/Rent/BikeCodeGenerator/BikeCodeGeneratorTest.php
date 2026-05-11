<?php

declare(strict_types=1);

namespace BikeShare\Test\Unit\Rent\BikeCodeGenerator;

use BikeShare\Rent\BikeCodeGenerator\BikeCodeGenerator;
use PHPUnit\Framework\TestCase;

class BikeCodeGeneratorTest extends TestCase
{
    public function testGeneratesFourDigitCodeInExpectedRange(): void
    {
        $generator = new BikeCodeGenerator();
        for ($i = 0; $i < 1000; $i++) {
            $code = $generator->generate();
            $this->assertMatchesRegularExpression('/^\d{4}$/', $code);
            $intVal = (int)$code;
            $this->assertGreaterThanOrEqual(100, $intVal);
            $this->assertLessThanOrEqual(9900, $intVal);
        }
    }
}
