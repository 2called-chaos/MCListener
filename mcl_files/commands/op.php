<?php

$aliases = '';

function CMD_op($MCL, $user, $params = array())
{
  if($MCL->isAdmin($user)) {
    if(count($params)) {
      $MCL->mcexec('op ' . $params[0]);
    } else {
      $MCL->mcexec('op ' . $user);
    }
  } else {
    $MCL->deny($user);
  }
}