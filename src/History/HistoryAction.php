<?php

declare(strict_types=1);

namespace BikeShare\History;

enum HistoryAction: string
{
    case RENT = 'RENT';
    case RETURN = 'RETURN';
    case REVERT = 'REVERT';
    case FORCERENT = 'FORCERENT';
    case FORCERETURN = 'FORCERETURN';
    case PHONE_CONFIRMED = 'PHONE_CONFIRMED';
    case PHONE_CONFIRM_REQUEST = 'PHONE_CONFIRM_REQUEST';
    case EMAIL_CONFIRMED = 'EMAIL_CONFIRMED';
    case CREDITCHANGE = 'CREDITCHANGE';
    case CREDIT = 'CREDIT';
}
