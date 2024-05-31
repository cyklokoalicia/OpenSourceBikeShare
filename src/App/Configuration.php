<?php

declare(strict_types=1);

namespace BikeShare\App;

class Configuration
{
    private array $params = [];

    public function __construct(string $filePath)
    {
        require $filePath;

        foreach (get_defined_vars() as $configKey => $configValue) {
            $this->params[$configKey] = $configValue;
        }
    }

    public function get($key)
    {
        return $this->params[$key] ?? null;
    }
}
