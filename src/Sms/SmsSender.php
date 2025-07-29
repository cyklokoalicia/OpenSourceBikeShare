<?php

namespace BikeShare\Sms;

use BikeShare\Db\DbInterface;
use BikeShare\SmsConnector\SmsConnectorInterface;

class SmsSender implements SmsSenderInterface
{
    public function __construct(
        private SmsConnectorInterface $smsConnector,
        private DbInterface $db,
    ) {
    }

    public function send($number, $message)
    {
        $maxMessageLength = $this->smsConnector->getMaxMessageLength();
        if (strlen($message) > $maxMessageLength) {
            $messageParts = str_split($message, $maxMessageLength);
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
        $this->db->query(
            'INSERT INTO sent SET number = :number, text = :message',
            [
                'number' => $number,
                'message' => $message
            ]
        );
    }
}
