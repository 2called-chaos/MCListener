<?php

$aliases = '';

if(!function_exists('CMD_reload')) {
  function CMD_reload($MCL, $user, $params = array())
  {
    if($MCL->isAdmin($user)) {
      $MCL->pm($user, 'Will reload script config!');
      $MCL->log('##### RELOAD #####');
      $MCL->tmp->rehash = true;
    } else {
      $MCL->deny($user);
    }
  }
}
