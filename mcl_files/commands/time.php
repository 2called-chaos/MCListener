<?php

$aliases = '';

if(!function_exists('CMD_time')) {
  function CMD_time($MCL, $user, $params = array())
  {
    if($MCL->isAdmin($user)) {
      // set time
      $MCL->time($user, count($params) ? $params[0] : null, count($params) ? $params[1] : null);
    } else {
      $MCL->deny($user);
    }
  }
}
