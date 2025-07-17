<?php

declare(strict_types=1);

namespace BikeShare\SmsConnector;

use Symfony\Component\HttpFoundation\RequestStack;

class EuroSmsConnector extends AbstractConnector
{
    private string $gatewayId = '';
    private string $gatewayKey = '';
    private string $gatewaySenderNumber = '';
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
        if (empty($config['gatewayId']) || empty($config['gatewayKey']) || empty($config['gatewaySenderNumber'])) {
            throw new \RuntimeException('Invalid EuroSms configuration');
        }
        $this->gatewayId = $config['gatewayId'];
        $this->gatewayKey = $config['gatewayKey'];
        $this->gatewaySenderNumber = $config['gatewaySenderNumber'];
    }

    // confirm SMS received to API
    public function respond()
    {
        if ($this->debugMode) {
            return;
        }
        echo 'ok:', $this->uuid, "\n";
    }

    // send SMS message via API
    public function send($number, $text): void
    {
        if ($this->debugMode) {
            return;
        }
        $s = substr(md5($this->gatewayKey . $number), 10, 11);
        $um = urlencode($text);
        $url = sprintf(
            'http://as.eurosms.com/sms/Sender?action=send1SMSHTTP&i=%s&s=%s&d=1&sender=%s&number=%s&msg=%s',
            $this->gatewayId,
            $s,
            $this->gatewaySenderNumber,
            $number,
            $um
        );
        fopen($url, "r");
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

    public static function getType(): string
    {
        return 'eurosms';
    }

    public function getMaxMessageLength(): int
    {
        return 160;
    }
}
