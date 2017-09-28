<?php

namespace BikeShare\Domain\Rent;

use BikeShare\Helpers\MyEnum;

class MethodType extends MyEnum
{
    const SMS = 'sms';
    const QR_CODE = 'qr_code';
    const WEB = 'web';
    const APP = 'mobile_app';
}
