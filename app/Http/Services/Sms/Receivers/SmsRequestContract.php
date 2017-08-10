<?php

namespace BikeShare\Http\Services\Sms\Receivers;


use Illuminate\Http\Request;

interface SmsRequestContract
{
    public function rules();

    /**
     * Primarily for testing
     * @param $smsText
     * @param $from
     * @param $receivedAt
     * @param $uuid
     * @return mixed
     */
    public function buildGetQuery($smsText, $from, $receivedAt, $uuid);

    public function smsModel(Request $request);
}