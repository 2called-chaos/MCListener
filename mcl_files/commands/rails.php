<?php

function CMD_rails($MCL, $user, $params = array())
{
  if($this->isTrusted($user)) {
    $MCL->mcexec('give ' . $user . ' 66 64');
    $MCL->mcexec('give ' . $user . ' 27 16');
  } else {
    $MCL->deny($user);
  }
}