<?php
namespace BikeShare\Http\Services\Sms\Drivers;

use Psr\Log\LoggerInterface;

class LogSmsDriver extends SmsService
{
    public $logger;


    public function __construct(LoggerInterface $logger)
    {
        $this->logger = $logger;
    }


    public function send($number, $text)
    {
        $this->logger->info('Send sms to ['.$number.'] with text:'.PHP_EOL.$text);
    }
}
