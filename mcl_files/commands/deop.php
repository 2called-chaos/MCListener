<?php

$aliases = '';

if(!function_exists('CMD_deop')) {
  function CMD_deop($MCL, $user, $params = array())
  {
    if($MCL->isAdmin($user)) {
      if(count($params)) {
        // deop other player
        $MCL->mcexec('deop ' . $params[0]);
      } else {
        // deop player itselfs
        $MCL->mcexec('deop ' . $user);
      }
    } else {
      $MCL->deny($user);
    }
  }
}
