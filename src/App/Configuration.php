<?php

declare(strict_types=1);

namespace BikeShare\App;

/**
 * @deprecated Should migrate to env
 */
class Configuration
{
    private array $params = [];

    public function __construct(string $filePath)
    {
        require_once $filePath;

        foreach (get_defined_vars() as $configKey => $configValue) {
            $this->params[$configKey] = $configValue;
        }
    }

    public function get($key)
    {
        return $this->params[$key] ?? null;
    }

    public function __call($name, $arguments)
    {
        return $this->params[$name] ?? null;
    }
}
