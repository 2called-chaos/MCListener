<?php

$aliases = '';

function CMD_restart($MCL, $user, $params = array())
{
  if($MCL->isAdmin($user)) {
    $MCL->pm($user, 'Will restart the server!');
    $MCL->fork(MC_PATH . '/mcl restart warn');
  } else {
    $MCL->deny($user);
  }
}