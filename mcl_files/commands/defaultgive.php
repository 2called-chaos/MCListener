<?php

function CMD_defaultgive($MCL, $user, $params = array())
{
  if(count($params) && is_numeric($params[0])) {
    $MCL->getUser($user)->settings->defaultGive = $params[0];
    $MCL->pm($user, 'Your default give amount was set to > ' . $params[0] . ' <!');
  } else {
    if(isset($MCL->getUser($user)->settings->defaultGive)) {
      $MCL->pm($user, 'Your default give amount is > ' . $MCL->getUser($user)->settings->defaultGive . ' <!');
    } else {
      $MCL->pm($user, 'Your default give amount is > ' . $MCL->defaultGiveAmount . ' <!');
    } 
  }
}