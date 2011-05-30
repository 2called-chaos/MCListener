<?php

$aliases = '';

function CMD_ping($MCL, $user, $params = array())
{
  $MCL->pm($user, 'Pong! (this means the script works)');
}