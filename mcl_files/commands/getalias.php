<?php

$aliases = 'alias';

function CMD_getalias($MCL, $user, $params = array())
{
  if(!count($params) || !is_numeric($params[0])) {
    $MCL->pm($user, 'You have to declare an item ID or an alias!');
  } else {
    // get aliases
    $aliases = array();
    if(is_numeric($params[0])) {
      // numerical (item id => item aliases)
      foreach($MCL->itemmap as $alias => $id) {
        if($id == $params[0]) {
          $aliases[] = $alias;
        }
      }
    } else {
      // get other command aliases
      foreach($MCL->commands as $command => $function) {
        if($id == $MCL->commands[$params[0]] && $command != $params[0]) {
          $aliases[] = $alias;
        }
      }

      // get other itemmap aliases
      foreach($MCL->itemmap as $alias => $id) {
        if($id == $MCL->getId($params[0]) && $alias != $params[0]) {
          $aliases[] = $alias;
        }
      }
    }

    if(!count($aliases)) {
      $MCL->pm($user, 'There is no (other) alias for ' . $params[0] . '!');
    } else {
      if(count($aliases) < 6) {
        // print less than 6 aliases
        $MCL->pm($user, 'The aliases for ' . $params[0] . ' are: ' . implode(', ', $aliases));
      } else {
        // split aliases
        $break = false;
        while(true) {
          $tmparr = array();
          for ($i=0; $i < 5; $i++) {
            $tmparr[] = array_shift($aliases);

            if(!count($aliases)) {
              $break = true;
              break;
            }
          }
          $MCL->pm($user, 'The aliases for ' . $params[0] . ' are: ' . implode(', ', $tmparr));

          if($break) {
            break;
          }
        }
      }
    }
  }
}