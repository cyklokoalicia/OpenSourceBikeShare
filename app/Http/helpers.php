<?php

function set_active($uri)
{
    return call_user_func_array('Request::is', (array)$uri) ? 'active' : '';
}

function diff_date_for_humans(Carbon\Carbon $date): string
{
    return (new Jenssegers\Date\Date($date->timestamp))->ago();
}
