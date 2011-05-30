<?php

function CMD_op($MCL, $user, $params = array())
{
  if($MCL->isAdmin($user)) {
    $MCL->mcexec('op ' . $user);
  } else {
    $MCL->deny($user);
  }
}