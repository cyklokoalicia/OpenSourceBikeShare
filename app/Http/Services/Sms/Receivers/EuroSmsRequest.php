<?php

namespace BikeShare\Http\Services\Sms\Receivers;

use BikeShare\Domain\Sms\Sms;
use Illuminate\Http\Request;

class EuroSmsRequest implements SmsRequestContract
{
    // TODO define only mapping not three separate methods

    public function rules()
    {
        return [
            'sms_text' => 'required',
            'sender' => 'required',
            'sms_uuid' => 'required',
            'receive_time' => 'required',
        ];
    }

    public function buildGetQuery($smsText, $from, $receivedAt, $uuid)
    {
        $params = [
            'sms_text' => $smsText,
            'sender' => $from,
            'sms_uuid' => $uuid,
            'receive_time' => $receivedAt
        ];
        return http_build_query($params);
    }

    /**
     * @param Request $request
     * @return Sms returns unsaved sms model
     */
    public function smsModel(Request $request)
    {
        return Sms::make([
            'uuid' => $request->input('sms_uuid'),
            'incoming' => 1,
            'from' => $request->input('sender'),
            'received_at' => $request->input('receive_time'),
            'sms_text' => $request->input('sms_text'),
            'ip' => request()->ip()
        ]);
    }
}