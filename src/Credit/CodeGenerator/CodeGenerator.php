<?php

namespace BikeShare\Credit\CodeGenerator;

class CodeGenerator implements CodeGeneratorInterface
{
    // exclude problem chars: B8G6I1l0OQDS5Z2
    private string $acceptableChars = 'ACEFHJKMNPRTUVWXY4937';

    public function generate($count, $length, $wastage = 25)
    {
        // build array allowing for possible wastage through duplicate values
        for ($i = 0; $i <= $count + $wastage + 1; $i++) {
            $codes[] = substr(str_shuffle($this->acceptableChars), 0, $length);
        }

        return array_slice($codes, 0, ($count));
    }
}
