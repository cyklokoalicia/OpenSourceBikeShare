<?php

use Symfony\Config\TwigConfig;

return static function (TwigConfig $twig) {
    $twig->global('siteName')->value('%env(APP_NAME)%');
};