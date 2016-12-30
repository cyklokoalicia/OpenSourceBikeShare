<?php

function set_active($uri)
{
    return call_user_func_array('Request::is', (array)$uri) ? 'active' : '';
}
