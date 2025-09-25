<?php

namespace BikeShare\Sms;

use BikeShare\Db\DbInterface;
use BikeShare\SmsConnector\SmsConnectorInterface;
use BikeShare\SmsTextNormalizer\SmsTextNormalizerInterface;
use Symfony\Component\Clock\ClockInterface;

class SmsSender implements SmsSenderInterface
{
    public function __construct(
        private readonly SmsConnectorInterface $smsConnector,
        private readonly SmsTextNormalizerInterface $smsTextNormalizer,
        private readonly DbInterface $db,
        private readonly ClockInterface $clock,
    ) {
    }

    public function send($number, $message)
    {
        $message = $this->smsTextNormalizer->normalize($message);
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
        $this->db->query(
            'INSERT INTO sent SET number = :number, text = :message, time = :time',
            [
                'number' => $number,
                'message' => $message,
                'time' => $this->clock->now()->format('Y-m-d H:i:s'),
            ]
        );
    }
}
