<?php

declare(strict_types=1);

namespace BikeShare\SmsConnector;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * http://textmagic.com
 * Create callback at: https://textmagic.com
 * URL for callback: http://example.com/receive.php (replace example.com with your website URL)
 */
class TextmagicSmsConnector extends AbstractConnector
{
    private const API_URL = 'https://www.textmagic.com/app/api';

    private string $gatewayUser = '';
    private string $gatewayPassword = '';
    private string $gatewaySenderNumber = '';

    public function __construct(
        private readonly RequestStack $requestStack,
        private readonly HttpClientInterface $httpClient,
        array $configuration,
    ) {
        parent::__construct($configuration);
    }

    public function checkConfig(array $config): void
    {
        if (
            empty($config['gatewayUser'])
            || empty($config['gatewayPassword'])
            || empty($config['gatewaySenderNumber'])
        ) {
            throw new \RuntimeException('Invalid Textmagic configuration');
        }

        $this->gatewayUser = $config['gatewayUser'];
        $this->gatewayPassword = $config['gatewayPassword'];
        $this->gatewaySenderNumber = $config['gatewaySenderNumber'];
    }

    // confirm SMS received to API
    public function respond(): string
    {
        return '';
    }

    // send SMS message via API
    public function send($number, $text): void
    {
        // The query carries the gateway password, so no message below may quote the URL,
        // and HttpClient's own transport errors (which do) are not chained.
        try {
            $statusCode = $this->httpClient->request(
                Request::METHOD_GET,
                self::API_URL,
                [
                    'query' => [
                        'cmd' => 'send',
                        'unicode' => 0,
                        'from' => $this->gatewaySenderNumber,
                        'username' => $this->gatewayUser,
                        'password' => $this->gatewayPassword,
                        'phone' => $number,
                        'text' => (string)$text,
                    ],
                ]
            )->getStatusCode();
        } catch (TransportExceptionInterface) {
            throw new \RuntimeException('Textmagic could not be reached to send the SMS to ' . $number);
        }

        if ($statusCode < Response::HTTP_OK || $statusCode >= Response::HTTP_MULTIPLE_CHOICES) {
            throw new \RuntimeException(
                'Textmagic did not accept the SMS to ' . $number . ': status code ' . $statusCode
            );
        }
    }

    public function receive(): void
    {
        if (is_null($this->requestStack->getCurrentRequest())) {
            throw new \RuntimeException('Could not receive sms in cli');
        }

        if ($this->requestStack->getCurrentRequest()->request->has('text')) {
            $this->message = $this->requestStack->getCurrentRequest()->request->get('text', '');
        }

        if ($this->requestStack->getCurrentRequest()->request->has('from')) {
            $this->number = $this->requestStack->getCurrentRequest()->request->get('from', '');
        }

        if ($this->requestStack->getCurrentRequest()->request->has('message_id')) {
            $this->uuid = $this->requestStack->getCurrentRequest()->request->get('message_id', '');
        }

        if ($this->requestStack->getCurrentRequest()->request->has('timestamp')) {
            $this->time = date("Y-m-d H:i:s", $this->requestStack->getCurrentRequest()->request->get('timestamp', ''));
        }

        if ($this->requestStack->getCurrentRequest()->server->has('REMOTE_ADDR')) {
            $this->ipaddress = $this->requestStack->getCurrentRequest()->server->get('REMOTE_ADDR');
        }
    }

    public static function getType(): string
    {
        return 'textmagic';
    }

    public function getMaxMessageLength(): int
    {
        return 160;
    }
}
