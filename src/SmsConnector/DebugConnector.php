<?php

declare(strict_types=1);

namespace BikeShare\SmsConnector;

use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Request;

class DebugConnector extends AbstractConnector
{
    private Request $request;
    private LoggerInterface $logger;

    public function __construct(
        Request $request,
        LoggerInterface $logger,
        array $configuration,
        $debugMode = false
    ) {
        parent::__construct($configuration, $debugMode);
        $this->request = $request;
        $this->logger = $logger;
    }

    public function checkConfig(array $config): void
    {
    }

    public function respond()
    {
    }

    public function send($number, $text): void
    {
        $this->logger->debug($number . ' -&gt ' . $text);
    }

    public function receive(): void
    {
        $this->message = $this->request->get('message');
        $this->number = $this->request->get('number');
        $this->uuid = $this->request->get('uuid');
        $this->time = $this->request->get('time');
        if ($this->request->server->has('REMOTE_ADDR')) {
            $this->ipaddress = $this->request->server->get('REMOTE_ADDR');
        }
    }

    public static function getType(): string
    {
        return 'debug';
    }
}
