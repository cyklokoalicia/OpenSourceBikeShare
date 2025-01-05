<?php

declare(strict_types=1);

return [
    \Symfony\Bundle\FrameworkBundle\FrameworkBundle::class => ['all' => true],
    \Symfony\Bundle\MonologBundle\MonologBundle::class => ['all' => true],
    \Symfony\Bundle\TwigBundle\TwigBundle::class  => ['all' => true],
    \Symfony\Bundle\SecurityBundle\SecurityBundle::class => ['all' => true],
    \Sentry\SentryBundle\SentryBundle::class => ['all' => true],
];