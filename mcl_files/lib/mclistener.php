<?php

/**
* Minecraft chat command listener based on the server.log
* by 2called-chaos (chaos@project-production.de)
* licensed under BY-NC-SA => http://creativecommons.org/licenses/by-nc-sa/3.0/
*/
class MCListener
{
  const VERSION = '0.1 (alpha build 273)';

  public $config = null;
  public $sys = null;
  public $tmp = null;

  public $base_dir = '/home/mc/one';
  public $mcl_dir = '/mcl_files';

  public $timemode = null;
  public $timemode_timer = 0;

  public function __construct($args)
  {
    set_time_limit(0);
    clearstatcache();
    date_default_timezone_set("Europe/Berlin");
    $this->mcl_dir = $this->base_dir . $this->mcl_dir;

    // init config & system
    $this->_initConfig($args);
    $this->_initSystem();

    // run handlers
    $this->_handleSingleton();
    $this->_handleCLI();

    // init additional configs
    $this->_initCommands();
    $this->_initItemMap();
    $this->_initItemKits();
    $this->_initTimes();
  }


  // ==========
  // = config =
  // ==========
  protected function _initSystem()
  {
    // structure
    $this->tmp = new stdClass;
    $this->tmp->mtime = null;
    $this->tmp->cmtime = null;
    $this->tmp->cmtime = null;
    $this->tmp->size = null;
    $this->tmp->csize = null;
    $this->tmp->newdata = null;
    
    $this->system = new stdClass;
    $this->system->serverlog = null;
    $this->system->mcllog = null;
    $this->system->commands = array();
    $this->system->itemmap = array();
    $this->system->kits = array();
    $this->system->times = array();
    $this->system->playerSettings = array();
  }

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
    $this->config->serverlog = null;
    $this->config->defaultGiveAmount = null;

    // set defaults
    $this->setDelay(0.5);
    $this->setScreenName('minecraft');
    $this->setPrefix('!');
    $this->config->defaultGiveAmount = 1;
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
    $this->system->mcllog = fopen($this->config->logfile, 'a');

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
        if(array_key_exists($alias, $this->system->itemmap)) {
          $this->error('warning', 'double alias ' . $alias . ' in itemmap (near line ' . ($lno + 1) . ')!');
        }

        $this->system->itemmap[$alias] = $id;
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
      if(array_key_exists($id, $this->system->kits)) {
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
            'amount' => $this->config->defaultGiveAmount,
          );
        }
      }

      $this->system->kits[$id] = $record;
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
      if(array_key_exists($id, $this->system->times)) {
        $this->error('warning', 'double time ' . $id . ' (near line ' . ($lno + 1) . ')!');
      }

      $this->system->times[$id] = $time;
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
        $this->system->commands[$command] = 'CMD_' . $command;
        $added++;

        // add aliases
        $parts = explode(',', $aliases);
        foreach($parts as $alias) {
          $alias = trim($alias);
          if(!empty($alias)) {
            $this->system->commands[trim($alias)] = 'CMD_' . $command;
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

    if(!array_key_exists($cmd, $this->system->commands)) {
      $this->pm($user, 'The command > ' . $cmd . ' < is not known!');
      $this->pm($user, 'Type > ' . $this->config->prefix . 'help < to get a list of available commands!');
    } else {
      $this->system->commands[$cmd]($this, $user, $params);
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

    if(is_resource($this->system->mcllog)) {
      return fwrite($this->system->mcllog, $entry . "\n");
    }

    return $this;
  }

  public function observe($file)
  {
    if(!file_exists($file)) {
      $this->log('The file "' . $file . '" was not found');
      throw new Exception('The file "' . $file . '" was not found');
    }

    $this->config->serverlog = $file;
    $this->system->handle = fopen($this->config->serverlog, "r");
    $this->tmp->mtime = filemtime($this->config->serverlog);
    $this->tmp->size = filesize($this->config->serverlog);

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
      $this->tmp->cmtime = filemtime($this->config->serverlog);
      $this->tmp->csize = filesize($this->config->serverlog);

      // timemode
      if(!is_null($this->timemode)) {
        if((time() - $this->timemode_timer) > 120) {
          $this->time($this->timemode);
          $this->timemode_timer = time();
        }
      }

      // nothing changed, sleep and wait for new data
      if ($this->tmp->mtime == $this->tmp->cmtime) {
        usleep($this->config->delay);
        continue;
      }

      // changed, open file and get new data
      $this->tmp->newdata = '';

      // seek to position and gt new data
      fseek($this->system->handle, $this->tmp->size);
      while ($data = fgets($this->system->handle)) {
        $this->tmp->newdata .= $data;
      }

      // update values
      $this->tmp->size = $this->tmp->csize;
      $this->tmp->mtime = $this->tmp->cmtime;

      // process new data
      $this->_processData();
    }
  }

  protected function _processData()
  {
    // preparation
    $data = trim($this->tmp->newdata);
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
    if(!array_key_exists($user, $this->system->playerSettings)) {
      $newuser = new stdClass;
      $newuser->settings = new stdClass;

      $this->system->playerSettings[$user] = $newuser;
    }

    return $this->system->playerSettings[$user];
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
    } elseif(array_key_exists($item, $this->system->kits)) {
      // kit
      $this->giveKit($user, $item);
      return;
    } elseif (!empty($item) && array_key_exists($item, $this->system->itemmap)) {
      // itemmap
      $item = $this->system->itemmap[$item];
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
        $amount = $this->config->defaultGiveAmount;
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
    $items = $this->system->kits[$kit];

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
          if(array_key_exists($time, $this->system->times)) {
            if($persist == 'perm') {
              $this->say("Timemode > " . $time . " < enabled!");
              $this->timemode = $time;
            }

            $this->time($this->system->times[$time]);
          } else {
            $this->pm($user, "Not valid value passed!");
          }
        }
      }
    }

    return $this;
  }
}