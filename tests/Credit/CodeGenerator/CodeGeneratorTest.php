<?php

namespace Test\BikeShare\Credit\CodeGenerator;

use BikeShare\Credit\CodeGenerator\CodeGenerator;
use PHPUnit\Framework\TestCase;

class CodeGeneratorTest extends TestCase
{
    private $acceptableChars = 'ACEFHJKMNPRTUVWXY4937';

    public function testGenerate()
    {
        $count = 10;
        $length = 8;
        $wastage = 25;

        $codeGenerator = new CodeGenerator();
        $codes = $codeGenerator->generate($count, $length, $wastage);
        $this->assertCount($count, $codes);
        foreach ($codes as $code) {
            $this->assertEquals($length, strlen($code));
            $this->assertRegExp('/^[' . $this->acceptableChars . ']{' . $length . '}$/', $code);
        }
    }
}
