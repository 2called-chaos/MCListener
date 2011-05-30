<?php

$aliases = 'id';

function CMD_getid($MCL, $user, $params = array())
{
  if(!count($params)) {
    $MCL->pm($user, 'You have to declare an alias!');
  } else {
    if(!array_key_exists($params[0], $MCL->system->itemmap)) {
      $MCL->pm($user, 'The alias >  ' . $params[0] . ' < was not found!');
    } else {
      $MCL->pm($user, 'The ID for ' . $params[0] . ' is ' . $MCL->system->itemmap[$params[0]]);
    }
  }
}