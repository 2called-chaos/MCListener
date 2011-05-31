<?php

/**
* Minecraft chat command listener based on the server.log
* by 2called-chaos (chaos@project-production.de)
* licensed under BY-NC-SA => http://creativecommons.org/licenses/by-nc-sa/3.0/
*/
class MCListener
{
  const VERSION = '0.2 (alpha build 370)';

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
    $this->cli->sendf("\n  %p--> MCListener " . self::VERSION . " <--%n");

    // run handlers
    $this->_handleSingleton();
    switch($this->_handleCLI()) {
      case 'exit':
        $this->cli->send('');
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
    $this->system->serverVersion = null;

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
    foreach($cfg as $key => $value) {
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
      $this->log('Will log to ' . $this->config->mcl_dir . '/output.log', 'log', false);
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
    foreach ($cfg as $id => $line) {
      $id = $id;
      $aliases = explode(',', $line);

      foreach ($aliases as $alias) {
        $alias = trim($alias);
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
    foreach ($cfg as $id => $kit) {
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
    foreach ($cfg as $id => $time) {
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
      $aliases = "";

      if(substr($command, 0, 1) != '_') {
        require($cmd);
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
    if(!isset($this->args[1])) {
      $this->_init('base', false);
      $cmd = 'screen -ls | egrep "(.*)\.' . $this->config->sysscreen . '[[:space:]](.*)"';
      $result = trim(`$cmd`);
      
      // start
      if(empty($result)) {
        $this->log("MCListener isn't running, start...", 'notice');
        $this->fork(MC_PATH . '/mcl launch', $this->config->sysscreen);
      }
      
      // reattach
      $this->log("Screen will be reattached. Press Ctrl+A Ctrl+D to detach...", 'notice', false);
      if($this->config->fastScreenAttach == 'yes') {
        usleep(500000);
      } else {
        sleep(3);
      }
      
      $cmd = 'screen -r ' . $this->config->sysscreen; `$cmd`;
      die;
    }
  }

  protected function _handleCLI()
  {
    if(isset($this->args[1])) {
      switch($this->args[1]) {
        case 'status':
          $this->_init('base', false);
          $this->log("%bMinecraft server seems to be " . ($this->online() ? '%gONLINE' : '%rOFFLINE'), 'notice');
          return 'exit';
        break;

        case 'start':
          $this->_init('base', false);
          $this->launch(isset($this->args[2]) ? $this->args[2] : null);
          return 'exit';
        break;

        case 'stop':
          $this->_init('base', false);
          $this->stop(isset($this->args[2]) ? $this->args[2] : null);
          return 'exit';
        break;

        case 'watch':
          $this->_init('base', false);
          $this->display();
          return 'exit';
        break;

        case 'restart':
          $this->_init('base', false);
          $this->restart(isset($this->args[2]) ? $this->args[2] : null);
          return 'exit';
        break;
        
        case 'halt':
          $this->log('Will halt...', 'notice');
          $counter = 0;
          file_put_contents(MC_PATH . '/mcl_files/tmp/halt', 'halt');
          
          while(true) {
            $cmd = 'screen -ls | egrep "(.*)\.' . $this->config->sysscreen . '[[:space:]](.*)"';
            $result = trim(`$cmd`);
            
            if($counter > 10) {
              $this->log('Failed halt...', 'fatal');
            }
            
            if(empty($result)) {
              break;
            } else {
              sleep(1);
              $counter++;
            }
          }
          return 'exit';
        break;
      }
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
    if(!$this->online()) {
      $this->log("Can't reattach minecraft console (server seems to be " . ($this->online() ? '%gONLINE' : '%rOFFLINE') . "%b)", 'warning');
      return;
    }

    $this->log("Screen will be reattached. Press Ctrl+A Ctrl+D to detach...", 'notice', false);
    if($this->config->fastScreenAttach == 'yes') {
      usleep(500000);
    } else {
      sleep(3);
    }

    // reattach screen
    $cmd = 'screen -r ' . $this->config->server->screen;
    return `$cmd`;
  }

  public function fork($cmd, $screen = null)
  {
    $cmd = 'screen -m -d -S ' . (is_null($screen) ? 'mcl_' . uniqid() : $screen) . ' ' . $cmd;
    return `$cmd`;
  }

  public function launch($force = false)
  {
    if($this->online()) {
      if($force == 'force') {
        $this->log("Server seems to be running, killing...", 'log');
        $this->stop($force);
      } else {
        $this->log("Couldn't start minecraft server (already running)!", 'fatal');
        return;
      }
    }

    $cmd = 'cd ' . MC_PATH; `$cmd`;

    $this->log("Launching minecraft server... ", 'notice', true, false);
    $cmd = 'screen -m -d -S ' . $this->config->server->screen
         . ' java -Xms' . strtolower($this->config->server->memalloc)
         . ' -Xmx' . strtolower($this->config->server->maxmemalloc)
         . ' ' . $this->config->server->args
         . ' -jar minecraft_server.jar nogui';
    `$cmd`; sleep(1);
    $this->cli->sendf("%gDONE!%n");

    if($this->config->server->displayOnLaunch == 'yes' || $force == 'watch') {
      $this->display();
    }
  }

  public function stop($force = false)
  {
    // not running => nothing to stop
    if(!$this->online()) {
      $this->log("Couldn't stop minecraft server (isn't running)!", 'fatal');
      return;
    }

    // init & warn
    $this->log("Stopping minecraft server... ", 'notice', true, false);
    if($force == 'warn') {
      $this->log('Server is running, warning players... 30', 'notice', true, false);
      $this->say('Server will stop in 30s!'); sleep(30);
      $this->cli->sendf('%g ... 10', false);
      $this->say('Server will stop in 10s!'); sleep(10);
      $this->cli->sendf('%g ... NOW');
      $this->say('Server will stop in NOW!');
    }

    // stop it
    $this->mcexec("stop");
    $counter = 0;

    // killing loop
    while(true) {
      $counter++;

      // offline => success
      if(!$this->online()) {
        break;
      }

      // limit exceeded => operation failed
      if($counter > 5) {
        if($force == 'force') {
          $this->cli->send('');
          $this->log("Couldn't shutdown server propably, forcing...", 'warning', true, false);
          $cmd = 'kill `ps -e | grep java | cut -d " " -f 1`'; `$cmd`;
          $cmd = 'rm -fr ' . MC_PATH . '/*.log.lck 2> /dev/null/'; `$cmd`;
          break;
        } else {
          $this->cli->send('');
          $this->log("Couldn't shutdown server propably!", 'fatal');
          return;
        }
      }

      // wait to shutdown
      sleep(1);
    }
    $this->cli->sendf("%gDONE (" . $counter . " tries)!%n");

    return $this;
  }

  public function restart($warn = false)
  {
    // kill if running
    if($this->online()) {
      if($warn == 'warn') {
        $this->log('Server is running, warning players... 30', 'notice', true, false);
        $this->say('Server will restart in 30s!'); sleep(30);
        $this->cli->sendf('%g ... 10', false);
        $this->say('Server will restart in 10s!'); sleep(10);
        $this->cli->sendf('%g ... NOW');
        $this->say('Server will restart in NOW!');
      }

      $this->stop();
    }

    $this->launch('force');
  }

  public function update($warn = false)
  {
    if(!$warn) {
      $this->log('Sorry, not implemented yet', 'notice', false);
      return;
      // mclistener
      $this->log('%bCheck for MCListener updates... ', 'notice', true, false);
      $mcl_current = $this->_check4updates('mcl');
      if(!$mcl_current) {
        $this->cli->sendf('%gDONE (no updates)%n');
      } else {
        $this->cli->sendf('%gDONE%n');
        $this->log('New updates available!');
        $this->log('Your version: ' . VERSION);
        $this->log('Current version: ' . $mcl_current);
      }
    } else {
      // do update
      switch($warn)
      {
        case 'mcl':
          $this->log('Sorry, not implemented yet', 'notice', false);
        break;
        case 'minecraft':
          $this->log('Sorry, not implemented yet', 'notice', false);
        break;
        default:
          $this->log('Illegal argument passed', 'fatal', false);
        break;
      }
    }
  }

  public function _check4updates($component)
  {
    switch($component) {
      case 'mcl':
        $currentVersion = file_get_contents("http://www.project-production.de/update/mclistener");
        if($currentVersion != VERSION) {
          return $currentVersion;
        } else {
          return false;
        }
      break;

      case 'minecraft':
        $currentVersion = file_get_contents("http://www.project-production.de/update/mclistener");
        if($currentVersion != VERSION) {
          return $currentVersion;
        } else {
          return false;
        }
      break;
    }
  }


  // =============
  // = internals =
  // =============
  public function log($entry, $level = "log", $log = true, $newline = true)
  {
    $levels = array(
      'log' => array(
        'color' => '%b',
        'contentcolor' => '%y',
        'fatal' => false,
      ),
      'notice' => array(
        'color' => '%g',
        'contentcolor' => '%g',
        'fatal' => false,
      ),
      'warning' => array(
        'color' => '%r',
        'contentcolor' => '%y',
        'fatal' => false,
      ),
      'fatal' => array(
        'color' => '%r',
        'contentcolor' => '%r',
        'fatal' => true,
      ),
    );

    // build strings
    $date = date("d.m.y H:i:s", time());
    $levelprefix = "[" . strtoupper($level) . "]";
    $clevelprefix = $levels[$level]['color'] . "[" . strtoupper($level) . "]%n";

    // log to stdout
    if($level == 'notice') {
      $this->cli->sendf("%y  " . $date . $levels[$level]['color'] . " * " . $levels[$level]['contentcolor'] . $entry . "%n", $newline);
    } else {
      $this->cli->sendf("%y  " . $date . "%n " . $clevelprefix . " " . $levels[$level]['contentcolor'] . $entry . "%n", $newline);
    }

    // log to file
    if($log && is_resource($this->system->mcllog)) {
      $entry = str_replace(array('%k', '%r', '%g', '%y', '%b', '%m', '%p', '%c', '%w'), '', $entry);

      if($level == 'notice') {
        return fwrite($this->system->mcllog, $date . " * " . $entry . "\n");
      } else {
        return fwrite($this->system->mcllog, $date . " " . $levelprefix . " " . $entry . "\n");
      }
    }

    // exit if fatal
    if($levels[$level]['fatal']) {
      $this->send('');
      die;
    }

    return $this;
  }

  protected function _init($mode = 'all', $log = true)
  {
    if($mode == 'base' || $mode == 'all') {
      // init config & system
      $this->_initSystem();
      $this->_initConfig();

      // init resources
      if($log) {
        $this->_initLogging();
      }
    }

    if($mode == 'all') {
      // init additional configs
      $this->_initCommands();
      $this->_initItemMap();
      $this->_initItemKits();
      $this->_initTimes();
    }
  }

  protected function _run()
  {
    while(true) {
      $this->_init();
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
      
      // rehash script
      if(isset($this->tmp->rehash)) {
        break;
      }
      
      // halt script
      if(file_exists(MC_PATH . '/mcl_files/tmp/halt')) {
        $this->log('Getting halt signal! Will halt now...', 'notice');
        unlink(MC_PATH . '/mcl_files/tmp/halt');
        sleep(2);
        die();
      }

      // timemode
      if(!is_null($this->timemode)) {
        if((time() - $this->timemode_timer) > 120) {
          $this->time('', $this->timemode);
          $this->timemode_timer = time();
        }
      }

      // check for updates in server.log
      $this->tmp->cmtime = filemtime($this->config->serverlog);
      $this->tmp->csize = filesize($this->config->serverlog);

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
      // player chat
      $re1 = '(?:(?:2|1)\\d{3}(?:-|\\/)(?:(?:0[1-9])|(?:1[0-2]))(?:-|\\/)(?:(?:0[1-9])|(?:[1-2][0-9])|'
           . '(?:3[0-1]))(?:T|\\s)(?:(?:[0-1][0-9])|(?:2[0-3])):(?:[0-5][0-9]):(?:[0-5][0-9]))';	# Timestamp
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

      // silent command calling via tell
      /*
        TODO linked
      */

      // server version
      /*
        TODO linked
      */
    }
  }


  // =======================
  // = users & permissions =
  // =======================
  public function &_getUser($user)
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

  public function deny($user)
  {
    $this->pm($user, 'You\'re not allowed to use this command!');

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
      $ouser = $this->_getUser($user);
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