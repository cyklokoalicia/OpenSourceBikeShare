<?php

function set_active($uri)
{
    return call_user_func_array('Request::is', (array)$uri) ? 'active' : '';
}

function diff_date_for_humans(Carbon\Carbon $date): string
{
    return (new Jenssegers\Date\Date($date->timestamp))->ago();
}

function dateFormat(Carbon\Carbon $date, string $format): string
{
    return (new Jenssegers\Date\Date($date->timestamp))->format($format);
}

function getStatusLabel($status)
{
    switch ($status) {
        case 'open':
        case 'free':
            return 'label-success';
        case 'setting':
            return 'label-warning';
        case 'occupied':
            return 'label-primary';
        case 'discard':
            return 'bg-fuchsia';
        case 'broken':
        case 'close':
            return 'label-danger';
        default:
            return 'label-info';
    }
}

function secToHours($seconds)
{
    $H = floor($seconds / 3600);
    $i = ($seconds / 60) % 60;
    $s = $seconds % 60;

    return sprintf("%02d:%02d:%02d", $H, $i, $s);
}

if (! function_exists('toastr')) {
    /**
     * Return the instance of toastr.
     *
     * @return Kamaln7\Toastr\Toastr
     */
    function toastr()
    {
        return app('toastr');
    }
}
