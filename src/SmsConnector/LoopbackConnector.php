<?php

declare(strict_types=1);

namespace BikeShare\SmsConnector;

use Symfony\Component\HttpFoundation\Request;

class LoopbackConnector extends AbstractConnector
{
    private array $store = [];
    private Request $request;

    public function __construct(
        Request $request,
        array $configuration,
        $debugMode = false
    ) {
        parent::__construct($configuration, $debugMode);
        $this->request = $request;
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
        $log = "<|~" . $this->request->query->has('sender') . "|~" . $this->message . "\n";
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
        if ($this->request->query->has('sms_text')) {
            $this->message = $this->request->query->get('sms_text');
        }
        if ($this->request->query->has('sender')) {
            $this->number = $this->request->query->get('sender');
        }
        if ($this->request->query->has('sms_uuid')) {
            $this->uuid = $this->request->query->get('sms_uuid');
        }
        if ($this->request->query->has('receive_time')) {
            $this->time = $this->request->query->get('receive_time');
        }
        if ($this->request->server->has('REMOTE_ADDR')) {
            $this->ipaddress = $this->request->server->get('REMOTE_ADDR');
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
