<?php

$aliases = '';

function CMD_day($MCL, $user, $params = array())
{
  $MCL->time('day', (count($params) ? $params[0] : null));
}