<?php

$aliases = '';

if(!function_exists('CMD_version')) {
  function CMD_version($MCL, $user, $params = array())
  {
    if($MCL->isAdmin($user)) {
      $MCL->pm($user, 'MCL Version: ' . MCListener::VERSION);
      $MCL->pm($user, 'Server Version: ' . !is_null($MCL->system->serverVersion) ? $MCL->system->serverVersion : 'unknown');
    } else {
      $MCL->deny($user);
    }
  }
}
