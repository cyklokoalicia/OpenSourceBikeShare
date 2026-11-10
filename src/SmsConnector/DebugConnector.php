<?php

declare(strict_types=1);

namespace BikeShare\SmsConnector;

use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Contracts\Service\ResetInterface;

class DebugConnector extends AbstractConnector implements ResetInterface
{
    private array $sentMessages = [];

    /** @var string[] */
    private array $failFor = [];

    public function __construct(
        private readonly RequestStack $requestStack,
        private readonly LoggerInterface $logger,
        array $configuration,
    ) {
        parent::__construct($configuration);
    }

    public function checkConfig(array $config): void
    {
    }

    public function respond(): string
    {
        return '';
    }

    public function send($number, $text): void
    {
        if (in_array($number, $this->failFor, true)) {
            throw new \RuntimeException('Debug delivery failure for ' . $number);
        }

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

    /**
     * Test hook: later sends to this number throw, as a gateway failure would.
     */
    public function failFor(string $number): void
    {
        $this->failFor[] = $number;
    }

    public function reset(): void
    {
        $this->sentMessages = [];
        $this->failFor = [];
    }
}
