<?php

/**
* Minecraft chat command listener based on the server.log
* by 2called-chaos (chaos@project-production.de)
* licensed under BY-NC-SA => http://creativecommons.org/licenses/by-nc-sa/3.0/
*/
class MCListener
{
  const VERSION = '0.1 (alpha build 221)';
  
  public $delay = null;
  public $screen = null;
  public $prefix = null;

  public $base_dir = '/home/mc/one';
  public $mcl_dir = '/mcl_files';

  public $file = null;
  public $handle = null;
  public $mtime = null;
  public $cmtime = null;
  public $size = null;
  public $csize = null;
  public $newdata = null;

  public $loghandle = null;
  public $logfile = null;
  public $argv = null;
  public $tmp = array();
  
  public $timemode = null;
  public $timemode_timer = 0;

  public $admins = array();
  public $trusted = array();
  public $playerSettings = array();
  public $defaultGiveAmount = 1;
  public $itemmap = array();
  public $kits = array();
  public $times = array();

  public function __construct($argv)
  {
    set_time_limit(0);
    clearstatcache();
    date_default_timezone_set("Europe/Berlin");
    
    $this->argv = $argv;
    $this->_handleSingleton();
    $this->_handleCLI();
    
    // set defaults
    $this->setDelay(0.5);
    $this->setScreenName('minecraft');
    $this->setPrefix('!');
    $this->mcl_dir = $this->base_dir . $this->mcl_dir;
    
    // run initializers
    $this->_initItemMap();
    $this->_initItemKits();
    $this->_initTimes();
  }

  protected function _handleSingleton()
  {
    
  }

  protected function _handleCLI()
  {
    // if(isset($this->argv[1])) {
    //   switch($this->argv[1]) {
    //     case '':
    //       
    //       die;
    //     break;
    //   }
    // } else {
    //   
    // }
  }
  
  protected function _initItemMap()
  {
    // get config file contents
    $cfg = file($this->mcl_dir . '/cfg_itemmap.ini');
    $added = 0;
    
    // parse itemmap
    foreach ($cfg as $lno => $line) {
      // skip comment lines
      if(substr($line, 0, 1) == '#') {
        continue;
      }
      
      $parts = explode('<=>', $line);
      $id = trim($parts[0]);
      $aliases = explode(',', $parts[1]);
      
      foreach ($aliases as $alias) {
        $alias = trim($alias);
        
        // check for double contents
        if(array_key_exists($alias, $this->itemmap)) {
          $this->error('warning', 'double alias ' . $alias . ' in itemmap (near line ' . ($lno + 1) . ')!');
        }
        
        $this->itemmap[$alias] = $id;
        $added++;
      }
    }
    
    $this->log('Added ' . $added . ' aliases to the itemmap!');
    
    // freeing space
    unset($cfg);
  }
  
  protected function _initItemKits()
  {
    // get config file contents
    $cfg = file($this->mcl_dir . '/cfg_kits.ini');
    $added = 0;
    
    // parse kits
    foreach ($cfg as $lno => $line) {
      // skip comment lines
      if(substr($line, 0, 1) == '#') {
        continue;
      }
      
      $parts = explode('=>', $line);
      $id = trim($parts[0]);
      $kit = explode('&', $parts[1]);

      // check for double kits
      if(array_key_exists($id, $this->kits)) {
        $this->error('warning', 'double kit ' . $id . ' (near line ' . ($lno + 1) . ')!');
      }
      
      // parse kit
      $record = array();
      foreach ($kit as $items) {
        $items = explode(':', trim($items));
        
        if(count($items) > 1) {
          $record[] = array(
            'item' => $items[0],
            'amount' => $items[1],
          );
        } else {
          $record[] = array(
            'item' => $items[0],
            'amount' => $this->defaultGiveAmount,
          );
        }
      }
      
      $this->kits[$id] = $record;
      $added++;
    }
    
    $this->log('Loaded ' . $added . ' kits!');
    
    // freeing space
    unset($cfg);
  }
  
  protected function _initTimes()
  {
    // get config file contents
    $cfg = file($this->mcl_dir . '/cfg_times.ini');
    $added = 0;
    
    // parse kits
    foreach ($cfg as $lno => $line) {
      // skip comment lines
      if(substr($line, 0, 1) == '#') {
        continue;
      }
      
      $parts = explode('=', $line);
      $id = trim($parts[0]);
      $time = trim($parts[1]);

      // check for double times
      if(array_key_exists($id, $this->times)) {
        $this->error('warning', 'double time ' . $id . ' (near line ' . ($lno + 1) . ')!');
      }
      
      $this->times[$id] = $time;
      $added++;
    }
    
    $this->log('Loaded ' . $added . ' kits!');
    
    // freeing space
    unset($cfg);
  }
  
  public function error($level, $message)
  {
    echo("\n[WARNING] " . $message . "\n");
    die;
  }

  public function setDelay($delay)
  {
    $this->delay = $delay * 1000000;

    return $this;
  }

  public function addAdmin($player)
  {
    $this->admins[] = $player;

    return $this;
  }

  public function addTrusted($player)
  {
    $this->trusted[] = $player;

    return $this;
  }

  public function setPrefix($prefix)
  {
    $this->prefix = $prefix;

    return $this;
  }

  public function enableLogging($file)
  {
    $this->logfile = $file;
    $this->loghandle = fopen($this->logfile, 'a');

    return $this;
  }

  public function setScreenName($screen)
  {
    $this->screen = $screen;

    return $this;
  }

  public function log($entry)
  {
    $entry = date("d.m.y H:i:s", time()) . " => " . $entry;

    echo "[LOG] " . $entry . "\n";

    if(is_resource($this->loghandle)) {
      return fwrite($this->loghandle, $entry . "\n");
    }

    return false;
  }

  public function mcexec($cmd)
  {
    $cmd = 'screen -S ' . $this->screen . ' -p 0 -X stuff "' . $cmd . "\r" . '"';
    return `$cmd`;
  }

  public function observe($file)
  {
    if(!file_exists($file)) {
      $this->log('The file "' . $file . '" was not found');
      throw new Exception('The file "' . $file . '" was not found');
    }

    $this->file = $file;
    $this->handle = fopen($this->file, "r");
    $this->mtime = filemtime($this->file);
    $this->size = filesize($this->file);

    $this->log('MCListener ' . self::VERSION . ' started');
    foreach($this->admins as $admin) {
      $this->pm($admin, 'MCListener ' . self::VERSION . ' started');
    }
    $this->_watchLog();

    return $this;
  }

  protected function _watchLog()
  {
    while (true) {
      clearstatcache();
      $this->cmtime = filemtime($this->file);
      $this->csize = filesize($this->file);

      // timemode
      if(!is_null($this->timemode)) {
        if((time() - $this->timemode_timer) > 120) {
          $this->time($this->timemode);
          $this->timemode_timer = time();
        }
      }

      // nothing changed, sleep and wait for new data
      if ($this->mtime == $this->cmtime) {
        usleep($this->delay);
        continue;
      }

      // changed, open file and get new data
      $this->newdata = '';

      // seek to position and gt new data
      fseek($this->handle, $this->size);
      while ($data = fgets($this->handle)) {
        $this->newdata .= $data;
      }

      // update values
      $this->size = $this->csize;
      $this->mtime = $this->cmtime;

      // process new data
      $this->_processData();
    }
  }

  protected function _processData()
  {
    // preparation
    $data = trim($this->newdata);
    $chunks = explode("\n", $data);

    foreach($chunks as $chunk) {
      $re1='(?:(?:2|1)\\d{3}(?:-|\\/)(?:(?:0[1-9])|(?:1[0-2]))(?:-|\\/)(?:(?:0[1-9])|(?:[1-2][0-9])|(?:3[0-1]))(?:T|\\s)(?:(?:[0-1][0-9])|(?:2[0-3])):(?:[0-5][0-9]):(?:[0-5][0-9]))';	# Timestamp
      $re2='(?:\\s+\\[INFO\\]\\s+)';	# square braces
      $re3='(<[^>]+>)';	# tag resp. player
      $re4='(?:\\s+)';	# white space
      $re5='((?:.+))';	# command

      if(preg_match_all("/".$re1.$re2.$re3.$re4.$re5."/is", $chunk, $matches)) {
        $user = str_replace(array('<', '>'), '', $matches[1][0]);
        $cmd = $matches[2][0];

        if(substr($cmd, 0, strlen($this->prefix)) == $this->prefix) {
          $raw = trim(substr($cmd, strlen($this->prefix)));
          $split = explode(" ", $raw);
          $cmd = array_shift($split);
          $this->cmd($user, $cmd, $split);
        }
      }
    }
  }

  public function pm($user, $message)
  {
    $this->mcexec('tell ' . $user . ' ' . $message);

    return $this;
  }

  public function say($message)
  {
    $this->mcexec('say  ' . $message);

    return $this;
  }

  public function &getUser($user)
  {
    if(!array_key_exists($user, $this->playerSettings)) {
      $newuser = new stdClass;
      $newuser->settings = new stdClass;
      
      $this->playerSettings[$user] = $newuser;
    }
    
    return $this->playerSettings[$user];
  }

  public function give($user, $item = null, $amount = null)
  {
    if(is_numeric($item)) {
      // ok all fine
    } elseif(array_key_exists($item, $this->kits)) {
      // kit
      $this->giveKit($user, $item);
      return;
    } elseif (!empty($item) && array_key_exists($item, $this->itemmap)) {
      // itemmap
      $item = $this->itemmap[$item];
    } else {
      $this->pm($user, 'You have to declare an item ID');
      return false;
    }
    
    // block bedrock
    if($item == 7) {
      $this->pm($user, 'no, not this one ;)');
      return;
    }
    
    // default amount if not set
    if(is_null($amount)) {
      $ouser = $this->getUser($user);
      if(isset($ouser->settings->defaultGive)) {
        $amount = $ouser->settings->defaultGive;
      } else {
        $amount = $this->defaultGiveAmount;
      }
    }
    
    // maximum amount
    $limitExceeded = false;
    if($amount > 2304) {
      $amount = 2304;
      $limitExceeded = true;
    }

    // give items
    $times = floor($amount / 64);
    $lamount = $amount - ($times * 64);

    // give stacks
    for ($i=0; $i < $times; $i++) {
      $this->mcexec('give ' . $user . ' ' . $item . ' 64');
    }

    // give rest
    if($lamount) {
      $this->mcexec('give ' . $user . ' ' . $item . ' ' . $lamount);
    }

    // send limit exceeded message if necessary 
    if($limitExceeded) {
      $this->pm($user, 'The amount is to high, you will get the maximum of 2304 items!');
    }

    return $this;
  }
  
  public function giveKit($user, $kit)
  {
    $items = $this->kits[$kit];
    
    // give items
    foreach($items as $item) {
      $this->give($user, $item['item'], $item['amount']);
    }
  }

  public function isAdmin($user)
  {
    return in_array($user, $this->admins);
  }

  public function isTrusted($user)
  {
    return in_array($user, $this->trusted) || $this->isAdmin($user);
  }

  public function deny($user)
  {
    $this->pm($user, 'You\'re not allowed to use this command!');

    return $this;
  }

  public function time($time = null, $persist = null)
  {
    if(is_null($time)) {
      $this->pm($user, "You have to pass a value! integer or timemode");
    } else {
      if(is_numeric($time)) {
        $this->mcexec('time set ' . $time);
      } else {
        if($time == 'normal') {
          $this->timemode = null;
          $this->say("Normal time now.");
        } else {
          if(array_key_exists($time, $this->times)) {
            if($persist == 'perm') {
              $this->say("Timemode > " . $time . " < enabled!");
              $this->timemode = $time;
            }
            
            $this->time($this->times[$time]);
          } else {
            $this->pm($user, "Not valid value passed!");
          }
        }
      }
    }
    
    return $this;
  }

  public function cmd($user, $cmd, $params = array())
  {
    if(empty($cmd)) {
      return false;
    }

    $this->log("$user called $cmd " . implode(' ', $params));

    switch($cmd) {
      case 'day':
        $this->time('day');
      break;

      ##########

      case 'night':
        $this->time('night');
      break;

      ##########

      case 'time':
        if($this->isAdmin($user)) {
          $this->time(count($params) ? $params[0] : null, count($params) ? $params[1] : false);
        } else {
          $this->deny($user);
        }
      break;

      ##########

      case 'dirt':
        if($this->isTrusted($user)) {
          $this->give($user, 3, 64);
        } else {
          $this->deny($user);
        }
      break;

      ##########

      case 'defaultgive':
        if(count($params) && is_numeric($params[0])) {
          $this->getUser($user)->settings->defaultGive = $params[0];
          $this->pm($user, 'Your default give amount was set to > ' . $params[0] . ' <!');
        } else {
          if(isset($this->getUser($user)->settings->defaultGive)) {
            $this->pm($user, 'Your default give amount is > ' . $this->getUser($user)->settings->defaultGive . ' <!');
          } else {
            $this->pm($user, 'Your default give amount is > ' . $this->defaultGiveAmount . ' <!');
          } 
        }
      break;

      ##########

      case 'getid':
        if(!count($params)) {
          $this->pm($user, 'You have to declare an alias!');
        } else {
          if(!array_key_exists($params[0], $this->itemmap)) {
            $this->pm($user, 'The alias >  ' . $params[0] . ' < was not found!');
          } else {
            $this->pm($user, 'The ID for ' . $params[0] . ' is ' . $this->itemmap[$params[0]]);
          }
        }
      break;
      
      ##########

      case 'getalias':
      case 'getaliases':
        if(!count($params) || !is_numeric($params[0])) {
          $this->pm($user, 'You have to declare an item ID!');
        } else {
          $aliases = array();
          foreach($this->itemmap as $alias => $id) {
            if($id == $params[0]) {
              $aliases[] = $alias;
            }
          }
          
          if(!count($aliases)) {
            $this->pm($user, 'There is no alias for ID ' . $params[0] . '!');
          } else {
            if(count($aliases) < 6) {
              $this->pm($user, 'The aliases for ID are: ' . implode(', ', $aliases));
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
                $this->pm($user, 'The aliases for ID are: ' . implode(', ', $tmparr));
                
                if($break) {
                  break;
                }
              }
            }
          }
        }
      break;

      ##########

      case 'give':
        if($this->isAdmin($user)) {
          $amount = null;

          if(array_key_exists(1, $params) && is_numeric($params[1])) {
            $amount = $params[1];
          }

          $this->give($user, isset($params[0]) ? $params[0] : null, $amount);
        } else {
          $this->deny($user);
        }
      break;

      ##########

      case 'rails':
        if($this->isTrusted($user)) {
          $this->mcexec('give ' . $user . ' 66 64');
          $this->mcexec('give ' . $user . ' 27 16');
        } else {
          $this->deny($user);
        }
      break;

      ##########

      case 'tp':
        if($this->isTrusted($user)) {
          $this->mcexec('tp ' . $user . ' ' . $params[0]);
        } else {
          $this->deny($user);
        }
      break;

      ##########

      // case 'players':
      // case 'users':
      // case 'userlist':
      // case 'playerlist':
      // case 'list':
      // case 'online':
      //   $players = $this->_cmd_getPlayers();
      //   // $this->mcexec('');
      // break;

      ##########

      case 'op':
        if($this->isAdmin($user)) {
          $this->mcexec('op ' . $user);
        } else {
          $this->deny($user);
        }
      break;

      ##########

      case 'deop':
        if($this->isAdmin($user)) {
          $this->mcexec('deop ' . $user);
        } else {
          $this->deny($user);
        }
      break;

      ##########

      case 'help':
      case '?':
        $this->pm($user, 'Available commands:');
        $this->pm($user, '!help !ping !day !midday !night !dirt !tp !rails');
        $this->pm($user, ' !give !op !deop !defaultgive !getid !getalias');
        // $this->pm($user, ' ');
      break;

      ##########

      case 'ping':
        $this->pm($user, 'Pong! (this means the script works)');
      break;

      ##########

      default:
        $this->mcexec('tell ' . $user . ' The command > ' . $cmd . ' < is not known!');
        $this->mcexec('tell ' . $user . ' Type > ' . $this->prefix . 'help < to get a list of available commands!');
      break;
    }
  }

  protected function _cmd_getPlayers()
  {

  }

  public function stop()
  {
    die("\n\nthe script died!\n\n");
  }
}