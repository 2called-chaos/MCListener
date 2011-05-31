<?php

$aliases = '';

function CMD_version($MCL, $user, $params = array())
{
  if($MCL->isAdmin($user)) {
    $this->pm($user, 'MCL Version: ' . VERSION);
    $this->pm($user, 'Server Version: ' . !is_null($this->system->serverVersion) ? $this->system->serverVersion : 'unknown');
  } else {
    $MCL->deny($user);
  }
}