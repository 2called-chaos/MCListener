<?php

function CMD_give($MCL, $user, $params = array())
{
  if($MCL->isAdmin($user)) {
    $amount = null;

    if(array_key_exists(1, $params) && is_numeric($params[1])) {
      $amount = $params[1];
    }

    $MCL->give($user, isset($params[0]) ? $params[0] : null, $amount);
  } else {
    $MCL->deny($user);
  }
}