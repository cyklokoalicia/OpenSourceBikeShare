<?php

use Symfony\Config\TwigConfig;

return static function (TwigConfig $twig) {
    $twig->global('siteName')->value('%env(APP_NAME)%');
    $twig->global('googleAnalyticsId')->value('%env(GOOGLE_ANALYTICS_ID)%');
    $twig->formThemes(['bootstrap_4_layout.html.twig']);
};
