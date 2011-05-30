<?php

$aliases = '';

function CMD_dirt($MCL, $user, $params = array())
{
  if($MCL->isTrusted($user)) {
    $MCL->give($user, 3, 64);
  } else {
    $MCL->deny($user);
  }
}