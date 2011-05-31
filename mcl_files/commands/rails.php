<?php

$aliases = '';

if(!function_exists('CMD_rails')) {
  function CMD_rails($MCL, $user, $params = array())
  {
    if($MCL->isTrusted($user)) {
      // give items
      $MCL->mcexec('give ' . $user . ' 66 64');
      $MCL->mcexec('give ' . $user . ' 27 16');
    } else {
      $MCL->deny($user);
    }
  }
}
