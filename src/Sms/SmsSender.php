<?php

namespace BikeShare\Sms;

use BikeShare\Db\DbInterface;
use BikeShare\SmsConnector\SmsConnectorInterface;

class SmsSender implements SmsSenderInterface
{
    /**
     * @var SmsConnectorInterface
     */
    private $smsConnector;
    /**
     * @var DbInterface
     */
    private $db;

    public function __construct(
        SmsConnectorInterface $smsConnector,
        DbInterface $db
    ) {
        $this->smsConnector = $smsConnector;
        $this->db = $db;
    }

    public function send($number, $message)
    {
        if (strlen($message) > 160) {
            $messageParts = str_split($message, 160);
            foreach ($messageParts as $text) {
                $text = trim($text);
                if ($text) {
                    $this->log($number, $text);
                    $this->smsConnector->send($number, $text);
                }
            }
        } else {
            $this->log($number, $message);
            $this->smsConnector->send($number, $message);
        }
    }

    private function log($number, $message)
    {
        $message = $this->db->escape($message);
        $this->db->query("INSERT INTO sent SET number='$number', text='$message'");
    }
}
