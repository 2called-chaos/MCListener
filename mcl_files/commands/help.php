<?php

function CMD_help($MCL, $user, $params = array())
{
  $MCL->pm($user, 'Available commands:');
  $MCL->pm($user, '!help !ping !day !midday !night !dirt !tp !rails');
  $MCL->pm($user, ' !give !op !deop !defaultgive !getid !getalias');
}