<?php

declare(strict_types=1);

namespace BikeShare\SmsConnector;

use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Contracts\Service\ResetInterface;

class DebugConnector extends AbstractConnector implements ResetInterface
{
    private ?Request $request;
    private LoggerInterface $logger;
    private array $sentMessages = [];

    public function __construct(
        ?Request $request,
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
        $this->sentMessages[] = $text;
    }

    public function receive(): void
    {
        if (is_null($this->request)) {
            throw new \RuntimeException('Could not receive sms in cli');
        }
        $this->message = $this->request->get('message', '');
        $this->number = $this->request->get('number', '');
        $this->uuid = $this->request->get('uuid', '');
        $this->time = $this->request->get('time', '');
        if ($this->request->server->has('REMOTE_ADDR')) {
            $this->ipaddress = $this->request->server->get('REMOTE_ADDR');
        }
    }

    public static function getType(): string
    {
        return 'debug';
    }

    public function getSentMessages(): array
    {
        return $this->sentMessages;
    }

    public function reset()
    {
        $this->sentMessages = [];
    }
}
