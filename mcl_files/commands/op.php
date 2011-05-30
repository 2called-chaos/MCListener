<?php

$aliases = '';

function CMD_op($MCL, $user, $params = array())
{
  if($MCL->isAdmin($user)) {
    if(count($params)) {
      // op other player
      $MCL->mcexec('op ' . $params[0]);
    } else {
      // op player itselfs
      $MCL->mcexec('op ' . $user);
    }
  } else {
    $MCL->deny($user);
  }
}