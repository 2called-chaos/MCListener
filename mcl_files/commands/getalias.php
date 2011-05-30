<?php

function CMD_getalias($MCL, $user, $params = array())
{
  if(!count($params) || !is_numeric($params[0])) {
    $MCL->pm($user, 'You have to declare an item ID!');
  } else {
    $aliases = array();
    foreach($MCL->itemmap as $alias => $id) {
      if($id == $params[0]) {
        $aliases[] = $alias;
      }
    }
    
    if(!count($aliases)) {
      $MCL->pm($user, 'There is no alias for ID ' . $params[0] . '!');
    } else {
      if(count($aliases) < 6) {
        $MCL->pm($user, 'The aliases for ID are: ' . implode(', ', $aliases));
      } else {
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
          $MCL->pm($user, 'The aliases for ID are: ' . implode(', ', $tmparr));
          
          if($break) {
            break;
          }
        }
      }
    }
  }
}