<?php

function CMD_time($MCL, $user, $params = array())
{
  if($MCL->isAdmin($user)) {
    $MCL->time(count($params) ? $params[0] : null, count($params) ? $params[1] : false);
  } else {
    $MCL->deny($user);
  }
  
}