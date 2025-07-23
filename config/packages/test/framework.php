<?php

declare(strict_types=1);

use Symfony\Config\FrameworkConfig;

return static function (FrameworkConfig $framework): void {
    $framework->test(true);

    $framework->session()
        ->storageFactoryId('session.storage.factory.mock_file')
    ;

    $framework->csrfProtection()
        ->enabled(false)
    ;
};
