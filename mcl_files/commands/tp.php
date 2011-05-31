<?php

$aliases = '';

if(!function_exists('CMD_tp')) {
  function CMD_tp($MCL, $user, $params = array())
  {
    if($MCL->isTrusted($user)) {
      // teleport to user
      if(count($params)) {
        $MCL->mcexec('tp ' . $user . ' ' . $params[0]);
      } else {
        $MCL->pm($user, 'You have to pass a destination user!');
      }
    
    } else {
      $MCL->deny($user);
    }
  }
}
