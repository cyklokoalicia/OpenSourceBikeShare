<?php


namespace BikeShare\Http\Services\Sms;


class SmsUtils
{

    public static function parseSmsArguments($smsText)
    {
        //preg_split must be used instead of explode because of multiple spaces
        return preg_split("/\s+/", strtoupper(trim(urldecode($smsText))));
    }

    public static function parseNoteFromReturnSms($smsText, $command)
    {
        if (preg_match("/{$command}[\s,\.]+[0-9]+[\s,\.]+[a-zA-Z0-9]+[\s,\.]+(.*)/i", $smsText, $matches)) {
            return trim($matches[1]);
        } else {
            return null;
        }
    }

    public static function parseNoteFromSms($smsText, $command)
    {
        if (preg_match("/{$command}[\s,\.]+[a-zA-Z0-9]+[\s,\.]+(.*)/i", $smsText, $matches)) {
            return trim($matches[1]);
        } else {
            return null;
        }
    }
}