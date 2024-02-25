<?php

namespace BikeShare\SmsConnector\SmsGateway;

class SmsGateway
{
    const BASE_URL = "https://smsgateway.me";


    public function __construct($email, $password)
    {
        $this->email = $email;
        $this->password = $password;
    }

    public function createContact($name, $number)
    {
        return $this->makeRequest('/api/v3/contacts/create', 'POST', ['name' => $name, 'number' => $number]);
    }

    public function getContacts($page = 1)
    {
        return $this->makeRequest('/api/v3/contacts', 'GET', ['page' => $page]);
    }

    public function getContact($id)
    {
        return $this->makeRequest('/api/v3/contacts/view/' . $id, 'GET');
    }


    public function getDevices($page = 1)
    {
        return $this->makeRequest('/api/v3/devices', 'GET', ['page' => $page]);
    }

    public function getDevice($id)
    {
        return $this->makeRequest('/api/v3/devices/view/' . $id, 'GET');
    }

    public function getMessages($page = 1)
    {
        return $this->makeRequest('/api/v3/messages', 'GET', ['page' => $page]);
    }

    public function getMessage($id)
    {
        return $this->makeRequest('/api/v3/messages/view/' . $id, 'GET');
    }

    public function sendMessageToNumber($to, $message, $device, $options = [])
    {
        $query = array_merge(['number' => $to, 'message' => $message, 'device' => $device], $options);
        return $this->makeRequest('/api/v3/messages/send', 'POST', $query);
    }

    public function sendMessageToManyNumbers($to, $message, $device, $options = [])
    {
        $query = array_merge(['number' => $to, 'message' => $message, 'device' => $device], $options);
        return $this->makeRequest('/api/v3/messages/send', 'POST', $query);
    }

    public function sendMessageToContact($to, $message, $device, $options = [])
    {
        $query = array_merge(['contact' => $to, 'message' => $message, 'device' => $device], $options);
        return $this->makeRequest('/api/v3/messages/send', 'POST', $query);
    }

    public function sendMessageToManyContacts($to, $message, $device, $options = [])
    {
        $query = array_merge(['contact' => $to, 'message' => $message, 'device' => $device], $options);
        return $this->makeRequest('/api/v3/messages/send', 'POST', $query);
    }

    public function sendManyMessages($data)
    {
        $query['data'] = $data;
        return $this->makeRequest('/api/v3/messages/send', 'POST', $query);
    }

    private function makeRequest($url, $method, $fields = [])
    {

        $fields['email'] = $this->email;
        $fields['password'] = $this->password;

        $url = self::BASE_URL . $url;

        $fieldsString = http_build_query($fields);


        $ch = curl_init();

        if ($method == 'POST') {
            curl_setopt($ch, CURLOPT_POST, count($fields));
            curl_setopt($ch, CURLOPT_POSTFIELDS, $fieldsString);
        } else {
            $url .= '?' . $fieldsString;
        }

        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_HEADER, false);  // we want headers
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

        $result = curl_exec($ch);

        $return['response'] = json_decode($result, true);

        if ($return['response'] == false) {
            $return['response'] = $result;
        }

        $return['status'] = curl_getinfo($ch, CURLINFO_HTTP_CODE);

        curl_close($ch);

        return $return;
    }
}
