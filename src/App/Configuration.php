<?php

namespace BikeShare\App;

class Configuration
{
    private array $params = [];

    public function __construct()
    {
        require __DIR__ . '/../../config.php';

        foreach (get_defined_vars() as $configKey => $configValue) {
            $this->params[$configKey] = $configValue;
        }
    }

    public function get($key)
    {
        return $this->params[$key] ?? null;
    }
}
