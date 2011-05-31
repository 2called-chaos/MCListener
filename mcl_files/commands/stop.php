<?php

$aliases = '';

if(!function_exists('CMD_stop')) {
  function CMD_stop($MCL, $user, $params = array())
  {
    if($MCL->isAdmin($user)) {
      $MCL->pm($user, 'Will stop the server!');
      $MCL->fork(MC_PATH . '/mcl stop warn');
    } else {
      $MCL->deny($user);
    }
  }
}
