<?php

$aliases = '';

if(!function_exists('CMD_ping')) {
  function CMD_ping($MCL, $user, $params = array())
  {
    $MCL->pm($user, 'Pong! (this means the script works)');
  }
}
