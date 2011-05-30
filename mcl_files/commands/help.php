<?php

$aliases = '?';

function CMD_help($MCL, $user, $params = array())
{
  $MCL->pm($user, 'Available commands:');

  $commands = array_keys($MCL->commands);
  $ccalls = array_values($MCL->commands);
  $used = array();

  // send commands splitted in to chunks
  while(count($commands)) {
    $str = '';
    while(strlen($str) < 45) {
      $val = array_shift($commands);
      $call = array_shift($ccalls);

      // skip double commands
      if(!array_key_exists($call, $used)) {
        $str .= $MCL->prefix . $val . ' ';
        $used[$call] = true;
      }

      // break if there're no more commands
      if(!count($commands)) {
        break;
      }
    }
    $MCL->pm($user, $str);
  }
}

