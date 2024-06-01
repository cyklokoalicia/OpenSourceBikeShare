<?php

namespace BikeShare\SmsConnector;

class LoopbackConnector extends AbstractConnector
{
    /**
     * @var array
     */
    private $store;

    public function __construct(
        array $config,
        $debugMode = false
    ) {
        $this->debugMode = $debugMode;
        $this->checkConfig($config);
        if (isset($_GET["sms_text"])) {
            $this->message = $_GET["sms_text"];
        }
        if (isset($_GET["sender"])) {
            $this->number = $_GET["sender"];
        }
        if (isset($_GET["sms_uuid"])) {
            $this->uuid = $_GET["sms_uuid"];
        }
        if (isset($_GET["receive_time"])) {
            $this->time = $_GET["receive_time"];
        }
        if (isset($_SERVER['REMOTE_ADDR'])) {
            $this->ipaddress = $_SERVER['REMOTE_ADDR'];
        }
    }

    public function checkConfig(array $config)
    {
        if ($this->debugMode) {
            return;
        }
        define('CURRENTDIR', dirname($_SERVER['SCRIPT_FILENAME']));
    }

    // confirm SMS received to API
    public function respond()
    {
        $log = "<|~" . $_GET["sender"] . "|~" . $this->message . "\n";
        foreach ($this->store as $message) {
            $log .= $message;
        }
        file_put_contents("connectors/loopback/loopback.log", $log, FILE_APPEND);
        unset($this->store);
    }

    // send SMS message via API
    public function send($number, $text)
    {
        $this->store[] = ">|~" . $number . "|~" . urlencode($text) . "\n";
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
