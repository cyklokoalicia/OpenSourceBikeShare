<?php

declare(strict_types=1);

use Symfony\Config\NelmioAliceConfig;

return static function (NelmioAliceConfig $nelmioAliceConfig): void {
    $nelmioAliceConfig
        ->locale('en_US')
        ->seed(1);
};
