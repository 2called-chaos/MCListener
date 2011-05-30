<?php

function CMD_deop($MCL, $user, $params = array())
{
  if($MCL->isAdmin($user)) {
    $MCL->mcexec('deop ' . $user);
  } else {
    $MCL->deny($user);
  }
}