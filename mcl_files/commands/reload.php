<?php

$aliases = '';

function CMD_reload($MCL, $user, $params = array())
{
  $MCL->pm($user, 'Will reload script config!');
  $MCL->tmp->rehash = true;
}