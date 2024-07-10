<?php

declare(strict_types=1);

use Symfony\Config\FrameworkConfig;

return static function (FrameworkConfig $framework) {
    $framework
        ->defaultLocale('en')
        ->enabledLocales(['en', 'sk', 'de', 'ua'])
        ->translator()
        ->defaultPath('%kernel.project_dir%/translations')
    ;
};