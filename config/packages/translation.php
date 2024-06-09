<?php

declare(strict_types=1);

use Symfony\Config\FrameworkConfig;

return static function (FrameworkConfig $framework) {
    $framework
        ->defaultLocale('en')
        ->translator()
        ->defaultPath('%kernel.project_dir%/translations')
    ;
};