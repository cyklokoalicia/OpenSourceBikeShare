<?php

declare(strict_types=1);

namespace BikeShare\SmsConnector;

use Symfony\Component\HttpFoundation\Request;

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
    private Request $request;

    public function __construct(
        Request $request,
        array $configuration,
        $debugMode = false
    ) {
        parent::__construct($configuration, $debugMode);
        $this->request = $request;
    }

    public function checkConfig(array $config): void
    {
        if ($this->debugMode) {
            return;
        }
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
        if ($this->debugMode) {
            return;
        }
        $um = urlencode($text);
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
        if ($this->request->request->has('text')) {
            $this->message = $this->request->request->get('text', '');
        }
        if ($this->request->request->has('from')) {
            $this->number = $this->request->request->get('from', '');
        }
        if ($this->request->request->has('message_id')) {
            $this->uuid = $this->request->request->get('message_id', '');
        }
        if ($this->request->request->has('timestamp')) {
            $this->time = date("Y-m-d H:i:s", $this->request->request->get('timestamp', ''));
        }
        if ($this->request->server->has('REMOTE_ADDR')) {
            $this->ipaddress = $this->request->server->get('REMOTE_ADDR');
        }
    }

    public static function getType(): string
    {
        return 'textmagic';
    }
}
