<?php

declare(strict_types=1);

use Symfony\Config\MonologConfig;

return static function (MonologConfig $monolog): void {
    $monolog->handler('test')
        ->type('test')
        ->level('debug');
};
