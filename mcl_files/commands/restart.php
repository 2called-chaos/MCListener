<?php

$aliases = '';

function CMD_reload($MCL, $user, $params = array())
{
  $MCL->pm($user, 'Will restart the server!');
  $MCL->fork(MC_PATH . '/mcl restart warn');
  $MCL->tmp->rehash = true;
}