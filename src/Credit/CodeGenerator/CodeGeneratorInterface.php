<?php

namespace BikeShare\Credit\CodeGenerator;

interface CodeGeneratorInterface
{
    public function generate($count, $length, $wastage);
}
