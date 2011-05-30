<?php

$aliases = 'dgive';

function CMD_defaultgive($MCL, $user, $params = array())
{
  if(count($params) && is_numeric($params[0])) {
    // set default give amount
    $MCL->getUser($user)->settings->defaultGive = $params[0];
    $MCL->pm($user, 'Your default give amount was set to > ' . $params[0] . ' <!');
  } else {
    if(isset($MCL->getUser($user)->settings->defaultGive)) {
      // print current user decided default give amount
      $MCL->pm($user, 'Your default give amount is > ' . $MCL->getUser($user)->settings->defaultGive . ' <!');
    } else {
      // print current default give amount
      $MCL->pm($user, 'Your default give amount is > ' . $MCL->config->defaultGiveAmount . ' < (system default)!');
    }
  }
}