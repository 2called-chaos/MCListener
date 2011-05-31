<?php

/**
* Minecraft chat command listener based on the server.log
* by 2called-chaos (chaos@project-production.de)
* licensed under BY-NC-SA => http://creativecommons.org/licenses/by-nc-sa/3.0/
*/
class MCListener
{
  const VERSION = '0.2 (alpha build 328)';

  public $args = array();
  public $cli = null;
  public $config = null;
  public $sys = null;
  public $tmp = null;

  public $timemode = null;
  public $timemode_timer = 0;

  public function __construct($args)
  {
    set_time_limit(0);
    clearstatcache();
    date_default_timezone_set("Europe/Berlin");
    $this->args = $args;
    
    // get CLI
    require_once(MC_PATH . '/mcl_files/lib/CLI/CLI.php');
    $this->cli = new Core_CLI;

    // run handlers
    $this->_handleSingleton();
    switch($this->_handleCLI()) {
      case 'exit':
        die;
      break;
    }
    
    $this->_run();
  }

  // ==========
  // = config =
  // ==========
  protected function _loadYML($file)
  {
    require_once(MC_PATH . '/mcl_files/lib/sfYaml/sfYaml.php');
    return sfYaml::load($file);
  }
  
  protected function _initSystem()
  {
    // structure
    $this->tmp = new stdClass;
    $this->tmp->mtime = null;
    $this->tmp->cmtime = null;
    $this->tmp->size = null;
    $this->tmp->csize = null;
    $this->tmp->newdata = null;

    $this->system = new stdClass;
    $this->system->serverlog = null;
    $this->system->mcllog = null;
    
    $this->tmp->admins = array('2called_chaos', 'DvdRom', 'Wo0T', 'Earl');
    $this->tmp->trusted = array('i81u812');
    $this->system->playerSettings = array();
  }

  protected function _initConfig()
  {
    // structure
    $this->config = new stdClass;
    
    // load config file
    $cfg = $this->_loadYML(MC_PATH . '/mcl_files/configs/config.ini');
    foreach($cfg['config'] as $key => $value) {
      if(is_array($value)) {
        $this->config->{$key} = new stdClass;
        foreach($value as $key2 => $value2) {
          $this->config->{$key}->{$key2} = $this->_filterConfig($value2);
        }
      } else {
        $this->config->{$key} = $this->_filterConfig($value);
      }
    }
    
    // run initializers
    $this->config->delay = $this->config->delay * 1000000;
    $this->config->minecraft_dir = MC_PATH;
    $this->config->mcl_dir = $this->config->minecraft_dir . '/mcl_files';
  }
  
  protected function _filterConfig($str)
  {
    if(is_string($str)) {
      $str = str_replace('%MC_PATH%', MC_PATH, $str);
    }
    
    
    return $str;
  }

  protected function _initLogging()
  {
    if($this->config->log == 'yes') {
      $this->system->mcllog = fopen($this->config->mcl_dir . '/output.log', 'a');
      $this->log('Will log to ' . $this->config->mcl_dir . '/output.log');
    }

    return $this;
  }

  protected function _initItemMap()
  {
    $this->system->itemmap = array();
    
    // get config file contents
    $cfg = $this->_loadYML($this->config->mcl_dir . '/configs/itemmap.ini');
    $added = 0;

    // parse itemmap
    foreach ($cfg['itemmap'] as $id => $line) {
      $id = $id;
      $aliases = explode(',', $line);

      foreach ($aliases as $alias) {
        $alias = trim($alias);

        // check for double contents
        if(array_key_exists($alias, $this->system->itemmap)) {
          $this->error('warning', 'double alias ' . $alias . ' in itemmap!');
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
    $this->system->kits = array();
    
    // get config file contents
    $cfg = $this->_loadYML($this->config->mcl_dir . '/configs/kits.ini');
    $added = 0;

    // parse kits
    foreach ($cfg['kits'] as $id => $kit) {
      // check for double kits
      if(array_key_exists($id, $this->system->kits)) {
        $this->error('warning', 'double kit ' . $id . '!');
      }

      // parse kit
      $record = array();
      foreach ($kit as $item => $amount) {
        $record[] = array(
          'item' => $item,
          'amount' => $amount,
        );
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
    $this->system->times = array();
    
    // get config file contents
    $cfg = $this->_loadYML($this->config->mcl_dir . '/configs/times.ini');
    $added = 0;

    // parse kits
    foreach ($cfg['times'] as $id => $time) {
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
    $this->system->commands = array();
    
    // get all commands
    $commands = glob($this->config->mcl_dir . '/commands/*.php');
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
    if(isset($this->args[1])) {
      switch($this->args[1]) {
      case 'status':
        if($this->online()) {
          echo "Minecraft server seems ONLINE."
          return 'exit';
        } else {
          echo "Minecraft server seems OFFLINE."
          return 'exit';
        }
        // if [ $ONLINE -eq 1 ]
        //           then
        //           echo "Minecraft server seems ONLINE."
        //         else
        //           echo "Minecraft server seems OFFLINE."
        //         fi;;
        //         
      break;
    }
  }

  protected function _handleCMD($user, $cmd, $params = array())
  {
    if(empty($cmd)) {
      return false;
    }

    // log
    $this->log("$user called $cmd " . implode(' ', $params));

    if(!array_key_exists($cmd, $this->system->commands)) {
      // try to get an item as last resort
      if(array_key_exists($cmd, $this->system->itemmap) || array_key_exists($cmd, $this->system->kits) || is_numeric($cmd)) {
        // item map
        $this->give($user, $cmd, count($params) ? $params[0] : null);
      } else {
        // nothing found
        $this->pm($user, 'The command > ' . $cmd . ' < is not known!');
        $this->pm($user, 'Type > ' . $this->config->prefix . 'help < to get a list of available commands!');
      }
    } else {
      $this->system->commands[$cmd]($this, $user, $params);
    }
  }


  // ==================
  // = daemon related =
  // ==================
  public function online()
  {
    clearstatcache();
    return file_exists(MC_PATH . '/server.log.lck');
  }
  
  public function display()
  {
    $cmd = 'screen -r ' . $this->config->server->screen;
    return `$cmd`;
  }
  
  public function launch()
  {
    $this->log("Launching minecraft server...");
    $cmd = 'cd ' . MC_PATH; `$cmd`;
    
    $cmd = 'screen -m -d -S ' . $this->config->server->screen
         . ' java -Xmx' . strtolower($this->config->server->memalloc)
         . ' -Xms' . strtolower($this->config->server->maxmemalloc)
         . ' ' . $this->config->server->args
         . ' -jar minecraft_server.jar nogui';
    `$cmd`; sleep(1);
  }
  
  public function stop()
  {
    $this->log("Stopping minecraft server...");
    $this->mcexec("stop");
    sleep(5);
  }

  // =============
  // = internals =
  // =============
  public function error($level, $message)
  {
    echo("\n[WARNING] " . $message . "\n");
    die;
  }

  public function log($entry, $level = "log")
  {
    $levels = array(
      'log' => array(
        'color' => '%p',
        'fatal' => false,
      ),
      'warning' => array(
        'color' => '%o',
        'fatal' => false,
      ),
      'fatal' => array(
        'color' => '%r',
        'fatal' => true,
      ),
    );
    
    $data = date("d.m.y H:i:s", time());
    $level = "[" . strtoupper($level) . "]";
    $clevel = $levels[$level]['color'] . "[" . strtoupper($level) . "]%n";
    
    // log to stdout
    $this->cli->sendf("%y  " . $date . " => %n" . $clevel . " %y" . $entry . "%n");

    if(is_resource($this->system->mcllog)) {
      return fwrite($this->system->mcllog, $date . " => " . $level . " " . $entry . "\n");
    }

    return $this;
  }

  protected function _run()
  {
    while(true) {
      // init config & system
      $this->_initSystem();
      $this->_initConfig();

      // init additional configs
      $this->_initCommands();
      $this->_initItemMap();
      $this->_initItemKits();
      $this->_initTimes();

      // init resources
      $this->_initLogging();

      // run
      $this->_observe();
    }
  }

  protected function _observe()
  {
    $this->config->serverlog = $this->config->minecraft_dir . '/server.log';
    $this->system->serverlog = fopen($this->config->serverlog, "r");
    $this->tmp->mtime = filemtime($this->config->serverlog);
    $this->tmp->size = filesize($this->config->serverlog);

    // send startet notification
    $this->log('MCListener ' . self::VERSION . ' started!');
    $this->log('#####');
    foreach($this->tmp->admins as $admin) {
      $this->pm($admin, 'MCListener ' . self::VERSION . ' started!');
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
          $this->time('', $this->timemode);
          $this->timemode_timer = time();
        }
      }

      // rehash script
      if(isset($this->tmp->rehash)) {
        break;
      }

      // nothing changed, sleep and wait for new data
      if ($this->tmp->mtime == $this->tmp->cmtime) {
        usleep($this->config->delay);
        continue;
      }

      // changed, open file and get new data
      $this->tmp->newdata = '';

      // seek to position and gt new data
      fseek($this->system->serverlog, $this->tmp->size);
      while ($data = fgets($this->system->serverlog)) {
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
    return in_array($user, $this->tmp->admins);
  }

  public function isTrusted($user)
  {
    return in_array($user, $this->tmp->trusted) || $this->isAdmin($user);
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
    $cmd = 'screen -S ' . $this->config->server->screen . ' -p 0 -X stuff "' . $cmd . "\r" . '"';
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

  public function time($user, $time = null, $persist = null)
  {
    if(is_null($time)) {
      $this->pm($user, "You have to pass a value! integer or timemode");
    } else {
      // normal time
      if($time === 'normal') {
        $this->timemode = null;
        $this->say("Normal time now.");
        return;
      }
      
      if($persist === 'perm') {
        // set timemode
        if(is_numeric($time)) {
          $this->timemode = $time;
          $this->time($user, $time);
        } else {
          if(array_key_exists($time, $this->system->times)) {
            $this->timemode = $this->system->times[$time];
            $this->time($user, $this->system->times[$time]);
          } else {
            $this->pm($user, "Not valid value passed!");
            return;
          }
        }
        
        $this->say("Timemode > " . $time . " < enabled!");
      } else {
        // set time
        if(is_numeric($time)) {
          $this->mcexec('time set ' . $time);
        } else {
          if(array_key_exists($time, $this->system->times)) {
            $this->time($user, $this->system->times[$time]);
          } else {
            $this->pm($user, "Not valid value passed!");
            return;
          }
        }
      }
    }

    return $this;
  }

}