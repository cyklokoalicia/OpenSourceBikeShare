<?php

declare(strict_types=1);

namespace BikeShare\SmsConnector;

use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Contracts\Service\ResetInterface;

class DebugConnector extends AbstractConnector implements ResetInterface
{
    private RequestStack $requestStack;
    private LoggerInterface $logger;
    private array $sentMessages = [];

    public function __construct(
        RequestStack $requestStack,
        LoggerInterface $logger,
        array $configuration,
        $debugMode = false
    ) {
        parent::__construct($configuration, $debugMode);
        $this->requestStack = $requestStack;
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
        $this->logger->debug($number . ' -> ' . $text);
        $this->sentMessages[] = [
            'number' => $number,
            'text' => $text,
        ];
    }

    public function receive(): void
    {
        if (is_null($this->requestStack->getCurrentRequest())) {
            throw new \RuntimeException('Could not receive sms in cli');
        }
        $this->message = $this->requestStack->getCurrentRequest()->get('message', '');
        $this->number = $this->requestStack->getCurrentRequest()->get('number', '');
        $this->uuid = $this->requestStack->getCurrentRequest()->get('uuid', '');
        $this->time = $this->requestStack->getCurrentRequest()->get('time', '');
        if ($this->requestStack->getCurrentRequest()->server->has('REMOTE_ADDR')) {
            $this->ipaddress = $this->requestStack->getCurrentRequest()->server->get('REMOTE_ADDR');
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
