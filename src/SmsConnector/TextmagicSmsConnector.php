<?php

declare(strict_types=1);

namespace BikeShare\SmsConnector;

use Symfony\Component\HttpFoundation\RequestStack;

/**
 * http://textmagic.com
 * Create callback at: https://textmagic.com
 * URL for callback: http://example.com/receive.php (replace example.com with your website URL)
 */
class TextmagicSmsConnector extends AbstractConnector
{
    private string $gatewayUser = '';
    private string $gatewayPassword = '';
    private string $gatewaySenderNumber = '';

    public function __construct(
        private readonly RequestStack $requestStack,
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
    public function respond()
    {
        // do nothing as no response required
    }

    // send SMS message via API
    public function send($number, $text): void
    {
        $um = urlencode((string) $text);
        $url = sprintf(
            "https://www.textmagic.com/app/api?cmd=send&unicode=0&from=%s&username=%s&password=%s&phone=%s&text=%s",
            $this->gatewaySenderNumber,
            $this->gatewayUser,
            $this->gatewayPassword,
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
