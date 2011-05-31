<?php

$aliases = '';

if(!function_exists('CMD_night')) {
  function CMD_night($MCL, $user, $params = array())
  {
    $MCL->time($user, 'night', (count($params) ? $params[0] : null));
  }
}
