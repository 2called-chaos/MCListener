<?php

$aliases = '';

function CMD_night($MCL, $user, $params = array())
{
  $MCL->time('night', (count($params) ? $params[0] : null));
}