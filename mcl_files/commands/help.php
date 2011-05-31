<?php

$aliases = '?';

if(!function_exists('CMD_help')) {
  function CMD_help($MCL, $user, $params = array())
  {
    $MCL->pm($user, 'Available commands:');

    // get commands
    $commands = array_keys($MCL->system->commands);
    $ccalls = array_values($MCL->system->commands);
    $used = array();

    // send commands splitted into chunks
    while(count($commands)) {
      $str = '';
      while(strlen($str) < 45) {
        $val = array_shift($commands);
        $call = array_shift($ccalls);

        // skip double commands
        if(!array_key_exists($call, $used)) {
          $str .= $MCL->config->prefix . $val . ' ';
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
}
