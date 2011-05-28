<?php

/**
* Minecraft chat command listener based on the server.log
* by 2called-chaos (chaos@project-production.de)
* licensed under BY-NC-SA => http://creativecommons.org/licenses/by-nc-sa/3.0/
*/
class MCListener
{
  const VERSION = '0.1 (alpha)';
  
  public $delay = null;
  public $screen = null;
  public $prefix = null;

  public $file = null;
  public $handle = null;
  public $mtime = null;
  public $cmtime = null;
  public $size = null;
  public $csize = null;
  public $newdata = null;

  public $loghandle = null;
  public $logfile = null;
  public $tmp = array();

  public $admins = array();
  public $trusted = array();
  public $playerSettings = array();
  public $defaultGiveAmount = 1;


  public function __construct()
  {
    set_time_limit(0);
    clearstatcache();
    date_default_timezone_set("Europe/Berlin");

    // set defaults
    $this->setDelay(0.5);
    $this->setScreenName('minecraft');
    $this->setPrefix('!');
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

  public function &getUser($user)
  {
    if(!array_key_exists($user, $this->playerSettings)) {
      $newuser = new stdClass;
      $newuser->settings = new stdClass;
      
      $this->playerSettings[$user] = $newuser;
    }
    
    return $this->playerSettings[$user];
  }

  public function give($user, $item, $amount = null)
  {
    // block bedrock
    if($item == 7) {
      $this->pm($user, 'no, not this one ;)');
      return;
    }
    
    // detect kit
    if(substr($item, 0, 1) == '#') {
      $this->giveKit($user, $item);
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

    for ($i=0; $i < $times; $i++) {
      $this->mcexec('give ' . $user . ' ' . $item . ' 64');
    }

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
    $items = array();
    
    // parse kit variable
    $kit = substr($kit, 1);
    $chunks = explode('+', $kit);
    
    foreach($chunks as $c) {
      $c = trim($c);
      $val = explode(':', $c);
      $items[$val[0]] = $val[1];
    }
    
    // give items
    foreach($items as $item => $amount) {
      if(is_numeric($item)) {
        $this->give($user, $item, $amount);
      } elseif (array_key_exists($item, $this->itemmap)) {
        $this->give($user, $this->itemmap[$item], $amount);
      } else {
        $this->pm($user, 'This item ID in this kit is wrong.');
      }
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

  public function cmd($user, $cmd, $params = array())
  {
    if(empty($cmd)) {
      return false;
    }

    $this->log("$user called $cmd " . implode(' ', $params));

    switch($cmd) {
      case 'day':
        $this->mcexec('time set 0');
      break;

      ##########

      case 'night':
        $this->mcexec('time set 13800');
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
          $this->pm($user, 'You need to declare a number as default give amount!');
        }
      break;

      ##########

      case 'give':
        if($this->isAdmin($user)) {
          $amount = null;

          if(array_key_exists(1, $params) && is_numeric($params[1])) {
            $amount = $params[1];
          }

          if(array_key_exists(0, $params)) {
            if(is_numeric($params[0])) {
              $item = $params[0];
              $this->give($user, $item, $amount);
            } elseif (array_key_exists($params[0], $this->itemmap)) {
              $item = $this->itemmap[$params[0]];
              $this->give($user, $item, $amount);
            } else {
              $this->pm($user, 'You have to declare an item ID');
            }
          }
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
        $this->pm($user, '!help !ping !day !night !dirt !tp !rails !give !op !deop');
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
