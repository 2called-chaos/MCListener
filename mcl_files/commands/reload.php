<?php

$aliases = '';

if(!function_exists('CMD_reload')) {
  function CMD_reload($MCL, $user, $params = array())
  {
    if($MCL->isAdmin($user)) {
      $MCL->pm($user, 'Will reload script config!');
      $MCL->log('##### RELOAD #####');
      file_put_contents(MC_PATH . '/mcl_files/tmp/reload', 'reload');
    } else {
      $MCL->deny($user);
    }
  }
}
