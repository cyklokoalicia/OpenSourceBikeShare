<?php

declare(strict_types=1);

use Symfony\Config\MonologConfig;

return static function (MonologConfig $monolog): void {
    $monolog->handler('BikeShare')
        ->appName('BikeShare')
        ->type('rotating_file')
        ->path('%kernel.project_dir%/var/log/log.log')
        ->maxFiles(30)
        ->level('notice');
};
