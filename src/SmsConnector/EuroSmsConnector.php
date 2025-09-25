<?php

declare(strict_types=1);

namespace BikeShare\SmsConnector;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class EuroSmsConnector extends AbstractConnector
{
    private string $gatewayId = '';
    private string $gatewayKey = '';
    private string $gatewaySenderNumber = '';
    private const API_HOST = 'https://as.eurosms.com/api/v3/send/one';
    private const API_HOST_TEST = 'https://as.eurosms.com/api/v3/test/one';

    public const FLAG_DELIVERY = 1; //Vyžiadanie generovanie doručenky u operátora
    public const FLAG_LONG_SMS = 2; //Očakávaná správa bude dlhšia ako 160, resp. 70 znakov.
    public const FLAG_DIACRITIC = 4; //Diakritika
    public const FLAG_HIGH_PRIORITY = 8; //Zvýšenie priority posielanej SMS.
    public const FLAG_LOW_PRIORITY = 32; //Využívava sa pre kampaňové správy.

    public function __construct(
        private readonly RequestStack $requestStack,
        private readonly HttpClientInterface $httpClient,
        array $configuration,
    ) {
        parent::__construct($configuration);
    }

    public function checkConfig(array $config): void
    {
        if (empty($config['gatewayId']) || empty($config['gatewayKey']) || empty($config['gatewaySenderNumber'])) {
            throw new \RuntimeException('Invalid EuroSms configuration');
        }

        $this->gatewayId = $config['gatewayId'];
        $this->gatewayKey = $config['gatewayKey'];
        $this->gatewaySenderNumber = $config['gatewaySenderNumber'];
    }

    // confirm SMS received to API
    public function respond(): void
    {
        echo 'ok:', $this->uuid, "\n";
    }

    // send SMS message via API
    public function send($number, $text): void
    {
        $signature = $this->calculateSignature($number, $text);
        $message = [
            'iid' => $this->gatewayId,
            'sgn' => $signature,
            'rcpt' => (int)$number,
            'flgs' => self::FLAG_DELIVERY|self::FLAG_LONG_SMS|self::FLAG_DIACRITIC,
            'sndr' => $this->gatewaySenderNumber,
            'txt' => $text,
        ];

        $response = $this->httpClient->request(
            Request::METHOD_POST,
            self::API_HOST,
            [
                'json' => $message,
            ]
        );

        $statusCode = $response->getStatusCode();

        if ($statusCode < 200 || $statusCode >= 300) {
            // Potentially log error response content: $response->getContent(false)
            throw new \RuntimeException("Failed to send SMS. API responded with status code: {$statusCode}");
        }
    }

    private function calculateSignature(string $number, string $text): string
    {
        $string = sprintf(
            '%s%s%s',
            $this->gatewaySenderNumber,
            $number,
            $text,
        );

        $hash = hash_hmac('sha256', $string, $this->gatewayKey);

        return $hash;
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
        return 1600;
    }
}
