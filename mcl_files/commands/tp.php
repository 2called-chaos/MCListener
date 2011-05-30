<?php

$aliases = '';

function CMD_tp($MCL, $user, $params = array())
{
  if($MCL->isTrusted($user)) {
    // teleport to user
    $MCL->mcexec('tp ' . $user . ' ' . $params[0]);
  } else {
    $MCL->deny($user);
  }
}