<?php

/**
* Minecraft chat command listener based on the server.log
* by 2called-chaos (chaos@project-production.de)
* licensed under BY-NC-SA => http://creativecommons.org/licenses/by-nc-sa/3.0/
*/
class MCListener
{
  const VERSION = '0.1 (alpha build 251)';

  public $config = null;

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
  public $tmp = array();

  public $timemode = null;
  public $timemode_timer = 0;

  public $playerSettings = array();
  public $defaultGiveAmount = 1;
  public $itemmap = array();
  public $kits = array();
  public $times = array();
  public $commands = array();

  public function __construct($args)
  {
    set_time_limit(0);
    clearstatcache();
    date_default_timezone_set("Europe/Berlin");
    $this->mcl_dir = $this->base_dir . $this->mcl_dir;

    // init config
    $this->_initConfig($args);

    // handlers
    $this->_handleSingleton();
    $this->_handleCLI();

    // run initializers
    $this->_initCommands();
    $this->_initItemMap();
    $this->_initItemKits();
    $this->_initTimes();
  }


  // ==========
  // = config =
  // ==========
  protected function _initConfig($args)
  {
    // structure
    $this->config = new stdClass;
    $this->config->args = $args;
    $this->config->delay = null;
    $this->config->screen = null;
    $this->config->admins = null;
    $this->config->trusted = null;
    $this->config->prefix = null;
    $this->config->logfile = null;

    // set defaults
    $this->setDelay(0.5);
    $this->setScreenName('minecraft');
    $this->setPrefix('!');
  }

  public function setDelay($delay)
  {
    $this->config->delay = $delay * 1000000;

    return $this;
  }

  public function addAdmin($player)
  {
    $this->config->admins[] = $player;

    return $this;
  }

  public function addTrusted($player)
  {
    $this->config->trusted[] = $player;

    return $this;
  }

  public function setPrefix($prefix)
  {
    $this->config->prefix = $prefix;

    return $this;
  }

  public function enableLogging($file)
  {
    $this->config->logfile = $file;
    $this->loghandle = fopen($this->config->logfile, 'a');

    return $this;
  }

  public function setScreenName($screen)
  {
    $this->config->screen = $screen;

    return $this;
  }


  // ===========
  // = loaders =
  // ===========
  protected function _initItemMap()
  {
    // get config file contents
    $cfg = file($this->mcl_dir . '/config/itemmap.ini');
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
    $cfg = file($this->mcl_dir . '/config/kits.ini');
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
    $cfg = file($this->mcl_dir . '/config/times.ini');
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

    $this->log('Loaded ' . $added . ' time modes!');

    // freeing space
    unset($cfg);
  }

  protected function _initCommands()
  {
    $commands = glob($this->mcl_dir . '/commands/*.php');
    $added = 0;
    $bounded = 0;

    foreach($commands as $cmd) {
      $command = basename($cmd, '.php');

      if(substr($command, 0, 1) != '_') {
        require_once($cmd);
        $this->commands[$command] = 'CMD_' . $command;
        $added++;

        // add aliases
        $parts = explode(',', $aliases);
        foreach($parts as $alias) {
          $alias = trim($alias);
          if(!empty($alias)) {
            $this->commands[trim($alias)] = 'CMD_' . $command;
            $bounded++;
          }
        }
      }
    }

    $this->log('Loaded ' . $added . ' commands with ' . ($added + $bounded) . ' bindings!');
  }


  // ============
  // = handlers =
  // ============
  protected function _handleSingleton()
  {

  }

  protected function _handleCLI()
  {
    // if(isset($this->args[1])) {
    //   switch($this->args[1]) {
    //     case '':
    //
    //       die;
    //     break;
    //   }
    // } else {
    //
    // }
  }

  protected function _handleCMD($user, $cmd, $params = array())
  {
    if(empty($cmd)) {
      return false;
    }

    // log
    $this->log("$user called $cmd " . implode(' ', $params));

    if(!array_key_exists($cmd, $this->commands)) {
      $this->pm($user, 'The command > ' . $cmd . ' < is not known!');
      $this->pm($user, 'Type > ' . $this->config->prefix . 'help < to get a list of available commands!');
    } else {
      $this->commands[$cmd]($this, $user, $params);
    }
  }


  // =============
  // = internals =
  // =============
  public function error($level, $message)
  {
    echo("\n[WARNING] " . $message . "\n");
    die;
  }

  public function log($entry)
  {
    $entry = date("d.m.y H:i:s", time()) . " => " . $entry;

    echo "[LOG] " . $entry . "\n";

    if(is_resource($this->loghandle)) {
      return fwrite($this->loghandle, $entry . "\n");
    }

    return $this;
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
    foreach($this->config->admins as $admin) {
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
        usleep($this->config->delay);
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

        if(substr($cmd, 0, strlen($this->config->prefix)) == $this->config->prefix) {
          $raw = trim(substr($cmd, strlen($this->config->prefix)));
          $split = explode(" ", $raw);
          $cmd = array_shift($split);
          $this->_handleCMD($user, $cmd, $split);
        }
      }
    }
  }
  
  public function stop()
  {
    die("\n\nthe script died!\n\n");
  }
  
  
  // =======================
  // = users & permissions =
  // =======================
  public function &getUser($user)
  {
    if(!array_key_exists($user, $this->playerSettings)) {
      $newuser = new stdClass;
      $newuser->settings = new stdClass;

      $this->playerSettings[$user] = $newuser;
    }

    return $this->playerSettings[$user];
  }

  public function isAdmin($user)
  {
    return in_array($user, $this->config->admins);
  }

  public function isTrusted($user)
  {
    return in_array($user, $this->config->trusted) || $this->isAdmin($user);
  }

  public function deny($user)
  {
    $this->pm($user, 'You\'re not allowed to use this command!');

    return $this;
  }

  
  // =================
  // = minecraft API =
  // =================
  public function mcexec($cmd)
  {
    $cmd = 'screen -S ' . $this->config->screen . ' -p 0 -X stuff "' . $cmd . "\r" . '"';
    return `$cmd`;
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
}