<?php

declare(strict_types=1);

namespace BikeShare\SmsConnector;

use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class EuroSmsConnector extends AbstractConnector
{
    private string $gatewayId = '';
    private string $gatewayKey = '';
    private string $gatewaySenderNumber = '';
    private const API_HOST = 'rest.eurosms.com';

    public function __construct(
        private readonly RequestStack $requestStack,
        private readonly HttpClientInterface $httpClient,
        array $configuration,
        $debugMode = false,
    ) {
        parent::__construct($configuration, $debugMode);
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

        $timestamp = time();
        $nonce = uniqid();
        $method = 'POST';
        $uri = '/v2/sms/';
        $port = 443;

        $mac = $this->calculateMac($timestamp, $nonce, $method, $uri, self::API_HOST, $port);

        $headers = [
            'Authorization' => sprintf(
                'MAC id="%s", ts="%s", nonce="%s", mac="%s"',
                $this->gatewayId,
                $timestamp,
                $nonce,
                $mac
            ),
            'Content-Type' => 'application/json',
        ];

        $payload = [
            'messages' => [
                [
                    'destination' => $number,
                    'message' => $text,
                    'origin' => $this->gatewaySenderNumber,
                ],
            ],
        ];

        $response = $this->httpClient->request($method, 'https://' . self::API_HOST . $uri, [
            'headers' => $headers,
            'json' => $payload,
        ]);

        $statusCode = $response->getStatusCode();

        if ($statusCode < 200 || $statusCode >= 300) {
            // Potentially log error response content: $response->getContent(false)
            throw new \RuntimeException("Failed to send SMS. API responded with status code: {$statusCode}");
        }
    }

    private function calculateMac(int $timestamp, string $nonce, string $method, string $uri, string $host, int $port): string
    {
        $requestString = sprintf(
            "%s\n%s\n%s\n%s\n%s\n%s\n\n",
            $timestamp,
            $nonce,
            $method,
            $uri,
            $host,
            $port
        );

        $hash = hash_hmac('sha256', $requestString, $this->gatewayKey, true);

        return base64_encode($hash);
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
