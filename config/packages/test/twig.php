<?php

use Symfony\Config\TwigConfig;

return static function (TwigConfig $twig) {
    $twig->strictVariables(true);
};
