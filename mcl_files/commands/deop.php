<?php

$aliases = '';

function CMD_deop($MCL, $user, $params = array())
{
  if($MCL->isAdmin($user)) {
    if(count($params)) {
      $MCL->mcexec('deop ' . $params[0]);
    } else {
      $MCL->mcexec('deop ' . $user);
    }
  } else {
    $MCL->deny($user);
  }
}