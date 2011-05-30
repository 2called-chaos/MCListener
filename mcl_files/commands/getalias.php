<?php

$aliases = 'alias';

function CMD_getalias($MCL, $user, $params = array())
{
  if(!count($params)) {
    // no query
    $MCL->pm($user, 'You have to declare an item ID or an alias/command!');
  } else {
    // get aliases
    $aliases = array();

    if(is_numeric($params[0])) {
      // numerical (item id => item aliases)
      foreach($MCL->system->itemmap as $alias => $id) {
        if($id == $params[0]) {
          $aliases[] = $alias;
        }
      }
    } else {
      // command aliases
      if(array_key_exists($params[0], $MCL->system->commands)) {
        foreach($MCL->system->commands as $command => $function) {
          if($function == $MCL->system->commands[$params[0]] && $command != $params[0]) {
            $aliases[] = $command;
          }
        }
      } else {
        // itemmap aliases
        foreach($MCL->system->itemmap as $alias => $id) {
          if(!array_key_exists($params[0], $MCL->system->itemmap)) {
            // alias not found anywhere
            $MCL->pm($user, 'The alias > ' . $params[0] . ' < was not found!');
            return;
          } elseif($MCL->system->itemmap[$params[0]] == $id && $alias != $params[0]) {
            $aliases[] = $alias;
          }
        }
      }
    }

    // output
    if(!count($aliases)) {
      // no other aliases
      $MCL->pm($user, 'There is no (other) alias for ' . $params[0] . '!');
    } else {
      if(count($aliases) < 6) {
        // print less than 6 aliases in one step
        $MCL->pm($user, 'The aliases for ' . $params[0] . ' are: ' . implode(', ', $aliases));
      } else {
        // split aliases
        $break = false;
        $tmparr = array_slice($aliases, 0, 5);
        $aliases = array_slice($aliases, 5);
        $MCL->pm($user, 'The aliases for ' . $params[0] . ' are: ' . implode(', ', $tmparr));

        // print rest
        while(true) {
          $tmparr = array();
          for ($i=0; $i < 5; $i++) {
            $tmparr[] = array_shift($aliases);

            if(!count($aliases)) {
              $break = true;
              break;
            }
          }
          $MCL->pm($user, implode(', ', $tmparr));

          if($break) {
            break;
          }
        }
      }
    }
  }
}