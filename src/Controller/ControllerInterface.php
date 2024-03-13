<?php

namespace BikeShare\Controller;

interface ControllerInterface
{
    /**
     * @return bool
     */
    public function checkAccess();

    /**
     * @return string
     */
    public function run();
}