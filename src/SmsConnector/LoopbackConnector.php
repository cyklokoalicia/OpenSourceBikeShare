<?php

declare(strict_types=1);

namespace BikeShare\SmsConnector;

use Symfony\Component\HttpFoundation\RequestStack;

class LoopbackConnector extends AbstractConnector
{
    private array $store = [];
    private RequestStack $requestStack;

    public function __construct(
        RequestStack $requestStack,
        array $configuration,
        $debugMode = false
    ) {
        parent::__construct($configuration, $debugMode);
        $this->requestStack = $requestStack;
    }

    public function checkConfig(array $config): void
    {
        if ($this->debugMode) {
            return;
        }
        define('CURRENTDIR', dirname($_SERVER['SCRIPT_FILENAME']));
    }

    // confirm SMS received to API
    public function respond()
    {
        $log = "<|~" . $this->requestStack->getCurrentRequest()->query->has('sender') . "|~" . $this->message . "\n";
        foreach ($this->store as $message) {
            $log .= $message;
        }
        file_put_contents("connectors/loopback/loopback.log", $log, FILE_APPEND);
        unset($this->store);
    }

    // send SMS message via API
    public function send($number, $text): void
    {
        $this->store[] = ">|~" . $number . "|~" . urlencode($text) . "\n";
    }

    public function receive(): void
    {
        if (is_null($this->requestStack->getCurrentRequest())) {
            throw new \RuntimeException('Could not receive sms in cli');
        }
        if ($this->requestStack->getCurrentRequest()->query->has('sms_text')) {
            $this->message = $this->requestStack->getCurrentRequest()->query->get('sms_text', '');
        }
        if ($this->requestStack->getCurrentRequest()->query->has('sender')) {
            $this->number = $this->requestStack->getCurrentRequest()->query->get('sender', '');
        }
        if ($this->requestStack->getCurrentRequest()->query->has('sms_uuid')) {
            $this->uuid = $this->requestStack->getCurrentRequest()->query->get('sms_uuid', '');
        }
        if ($this->requestStack->getCurrentRequest()->query->has('receive_time')) {
            $this->time = $this->requestStack->getCurrentRequest()->query->get('receive_time', '');
        }
        if ($this->requestStack->getCurrentRequest()->server->has('REMOTE_ADDR')) {
            $this->ipaddress = $this->requestStack->getCurrentRequest()->server->get('REMOTE_ADDR');
        }
    }

    // if Respond is not called, this forces the log to save / flush
    public function __destruct()
    {
        $log = "";
        if (isset($this->store) and is_array($this->store)) {
            foreach ($this->store as $message) {
                $log .= $message;
            }
            file_put_contents(CURRENTDIR . "/connectors/loopback/loopback.log", $log, FILE_APPEND);
        }
    }

    public static function getType(): string
    {
        return 'loopback';
    }
}
