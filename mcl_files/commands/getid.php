<?php

$aliases = 'id';

function CMD_getid($MCL, $user, $params = array())
{
  if(!count($params)) {
    $this->pm($user, 'You have to declare an alias!');
  } else {
    if(!array_key_exists($params[0], $this->itemmap)) {
      $this->pm($user, 'The alias >  ' . $params[0] . ' < was not found!');
    } else {
      $this->pm($user, 'The ID for ' . $params[0] . ' is ' . $this->itemmap[$params[0]]);
    }
  }
}