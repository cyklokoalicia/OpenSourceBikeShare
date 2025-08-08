<?php

declare(strict_types=1);

namespace BikeShare\Enum;

enum Action: string
{
    case RENT = 'RENT';
    case RETURN = 'RETURN';
    case REVERT = 'REVERT';
    case FORCE_RENT = 'FORCERENT';
    case FORCE_RETURN = 'FORCERETURN';
    case PHONE_CONFIRMED = 'PHONE_CONFIRMED';
    case PHONE_CONFIRM_REQUEST = 'PHONE_CONFIRM_REQUEST';
    case EMAIL_CONFIRMED = 'EMAIL_CONFIRMED';
    case CREDIT_CHANGE = 'CREDITCHANGE';
    case CREDIT = 'CREDIT';
}
