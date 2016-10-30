<?php
namespace BikeShare\Http\Services\Sms;

use GuzzleHttp\Client;
use Psr\Http\Message\ResponseInterface;
use GuzzleHttp\Exception\RequestException;

class EuroSms
{

    public $id;

    public $key;

    public $senderNumber;

    public $apiUrl = 'http://as.eurosms.com/sms/Sender';


    public function __construct($id, $key, $senderNumber)
    {
        $this->id = $id;
        $this->key = $key;
        $this->senderNumber = $senderNumber;
        $this->client = new Client();
    }


    public function makeRequest($number, $text)
    {
        $promise = $this->client->requestAsync('GET', $this->apiUrl, [
            'query' => [
                'action' => 'basend1SMSHTTP',
                'i'      => $this->id,
                's'      => substr(md5($this->key . $number), 10, 11),
                'd'      => 1,
                'sender' => $this->senderNumber,
                'number' => $number,
                'msg'    => urlencode($text)

            ]
        ]);
        $promise->then(
            function (ResponseInterface $res) {
                echo $res->getStatusCode() . "\n";
            },
            function (RequestException $e) {
                echo $e->getMessage() . "\n";
                echo $e->getRequest()->getMethod();
            }
        );
    }
}
