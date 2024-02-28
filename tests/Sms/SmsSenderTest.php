<?php

namespace Test\BikeShare\Sms;

use BikeShare\Db\DbInterface;
use BikeShare\Sms\SmsSender;
use BikeShare\SmsConnector\SmsConnectorInterface;
use PHPUnit\Framework\TestCase;

class SmsSenderTest extends TestCase
{
    protected function setUp()
    {
        $this->smsConnector = $this->createMock(SmsConnectorInterface::class);
        $this->db = $this->createMock(DbInterface::class);
        $this->smsSender = new SmsSender($this->smsConnector, $this->db);
    }

    protected function tearDown()
    {
        unset(
            $this->smsConnector,
            $this->db,
            $this->smsSender
        );
    }

    public function testSendShort()
    {
        $number = '123456789';
        $message = 'Hello, World!';
        $this->smsConnector
            ->expects($this->once())
            ->method('send')
            ->with($number, $message);
        $this->db
            ->expects($this->once())
            ->method('query')
            ->with("INSERT INTO sent SET number='$number', text='$message'");
        $this->db
            ->expects($this->once())
            ->method('commit');
        $this->smsSender->send($number, $message);
    }

    public function testSendBig()
    {
        $number = '123456789';
        $message = 'Hello, World! Lorem ipsum dolor sit amet, consectetur adipiscing elit. Nulla nec purus euismod mi fermentum sollicitudin. Vivamus euismod, tellus ac euismod       ultricies, justo risus luctus ipsum, quis condimentum orci lacus id tellus. Sed ut ultrices mi. Nullam id orci ut mauris tincidunt tincidunt. ';
        $this->smsConnector
            ->expects($this->exactly(2))
            ->method('send')
            ->withConsecutive(
                [$number, 'Hello, World! Lorem ipsum dolor sit amet, consectetur adipiscing elit. Nulla nec purus euismod mi fermentum sollicitudin. Vivamus euismod, tellus ac euismod'],
                [$number, 'ultricies, justo risus luctus ipsum, quis condimentum orci lacus id tellus. Sed ut ultrices mi. Nullam id orci ut mauris tincidunt tincidunt.']
            );
        $this->db
            ->expects($this->exactly(2))
            ->method('query')
            ->withConsecutive(
                ["INSERT INTO sent SET number='$number', text='Hello, World! Lorem ipsum dolor sit amet, consectetur adipiscing elit. Nulla nec purus euismod mi fermentum sollicitudin. Vivamus euismod, tellus ac euismod'"],
                ["INSERT INTO sent SET number='$number', text='ultricies, justo risus luctus ipsum, quis condimentum orci lacus id tellus. Sed ut ultrices mi. Nullam id orci ut mauris tincidunt tincidunt.'"]
            );
        $this->db
            ->expects($this->exactly(2))
            ->method('commit');
        $this->smsSender->send($number, $message);
    }
}
