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
  public $defaultGiveAmount = 25;

  // {
    public $itemmap = array(
      'air'     => 0,
      'stone'   => 1,
      'grass'   => 2,
      'dirt'    => 3,
      'cobble'  => 4,
      'cobblestone'  => 4,
      'plank'  => 5,
      'sapling'  => 6,
      'bedrock'  => 7,
      'water'  => 8,
      'swater'  => 9,
      'lava'  => 10,
      'slava'  => 11,
      'sand'  => 12,
      'gravel'  => 13,
      'goldore'  => 14,
      'gore'  => 14,
      'ironore'  => 15,
      'iore'  => 15,
      'coalore'  => 16,
      'core'  => 16,
      'wood'  => 17,
      'leaves'  => 18,
      'sponge'  => 19,
      'glass'  => 20,
      'lapisore'  => 21,
      'lapisblock'  => 22,
      'dispenser'  => 23,
      'sandstone'  => 24,
      'noteblock'  => 25,
      'bedblock'  => 26,
      'powerrail'  => 27,
      'prail'  => 27,
      'detectorrail'  => 28,
      'drail'  => 28,
      'web'  => 30,
      'tallgrass'  => 31,
      'tgrass'  => 31,
      'deadshrubs'  => 32,
      'dshrubs'  => 32,
      'shrubs'  => 32,
      'wool'  => 35,
      'dandelion'  => 37,
      'yflower'  => 37,
      'rose'  => 38,
      'rflower'  => 38,
      'brownmushroom'  => 39,
      'bmushroom'  => 39,
      'redmushroom'  => 40,
      'rmushroom'  => 40,
      'goldblock'  => 41,
      'gblock'  => 41,
      'ironblock'  => 42,
      'iblock'  => 42,
      'doubleslaps'  => 43,
      'dslaps'  => 43,
      'slaps'  => 44,
      'brickblock'  => 45,
      'bblock'  => 45,
      'tnt'  => 46,
      'bookshelf'  => 47,
      'mossstone'  => 48,
      'mstone'  => 48,
      'moss'  => 48,
      'obsidian'  => 49,
      'torch'  => 50,
      'fire'  => 51,
      'monsterspawner'  => 52,
      'spawner'  => 52,
      'woodenstairs'  => 53,
      'wstairs'  => 53,
      'chest'  => 54,
      'redstonewire'  => 55,
      'wire'  => 55,
      'diamondore'  => 56,
      'dore'  => 56,
      'diamondblock'  => 57,
      'dblock'  => 57,
      'craftingtable'  => 58,
      'workbench'  => 58,
      'seedblock'  => 59,
      'farmland'  => 60,
      'furnance'  => 61,
      'oven'  => 61,
      'burningfurnance'  => 62,
      'bfurnance'  => 62,
      'burningoven'  => 62,
      'boven'  => 62,
      'signpost'  => 63,
      'woodendoorblock'  => 64,
      'wdoorblock'  => 64,
      'ladder'  => 65,
      'ladders'  => 65,
      'rail'  => 66,
      'rails'  => 66,
      'cobblestonestairs'  => 67,
      'cobblestairs'  => 67,
      'cstairs'  => 67,
      'wallsign'  => 68,
      'lever'  => 69,
      'stonepresureplate'  => 70,
      'stoneplate'  => 70,
      'spresureplate'  => 70,
      'splate'  => 70,
      'irondoorblock'  => 71,
      'idoorblock'  => 71,
      'woodenpresureplate'  => 72,
      'woodenplate'  => 72,
      'wpresureplate'  => 72,
      'wplate'  => 72,
      'redstoneore'  => 73,
      'rore'  => 73,
      'glowingredstoneore'  => 74,
      'gredstoneore'  => 74,
      'grore'  => 74,
      'glowingrore'  => 74,
      'redstonetorch'  => 75,
      'rtorch'  => 75,
      'redstonetorchoff'  => 76,
      'rtorchoff'  => 76,
      'stonebutton'  => 77,
      'button'  => 77,
      'snow'  => 78,
      'ice'  => 79,
      'snowblock'  => 80,
      'sblock'  => 80,
      'cactus'  => 81,
      'clayblock'  => 82,
      'cblock'  => 82,
      'sugarcane'  => 83,
      'jukebox'  => 84,
      'fence'  => 85,
      'pumpkin'  => 86,
      'netherrack'  => 87,
      'soulsand'  => 88,
      'glowstone'  => 89,
      'portal'  => 90,
      'pumpkinlantern'  => 91,
      'plantern'  => 91,
      'jackolantern'  => 91,
      'cakeblock'  => 92,
      'redstonerepeaterblock'  => 93,
      'repeaterblock'  => 93,
      'redstonerepeateroffblock'  => 94,
      'redstonerepeateroff'  => 94,
      'repeateroffblock'  => 94,
      'repeateroff'  => 94,
      'lockedchest'  => 95,
      'trapdoor'  => 96,
      'tdoor'  => 96,
    
    
      'ironshovel' => 256,
      'ishovel' => 256,
      'ironpickaxe' => 257,
      'ironpick' => 257,
      'ipickaxe' => 257,
      'ipick' => 257,
      'ironaxe' => 258,
      'iaxe' => 258,
      'flintandsteel' => 259,
      'flintsteel' => 259,
      'apple' => 260,
      'bow' => 261,
      'arrow' => 262,
      'arrows' => 262,
      'coal' => 263,
      'diamond' => 264,
      'ironingot' => 265,
      'iron' => 265,
      'goldingot' => 266,
      'gold' => 266,
      'ironsword' => 267,
      'isword' => 267,
      'woodensword' => 268,
      'wsword' => 268,
      'woodenshovel' => 269,
      'wshovel' => 269,
      'woodenpickaxe' => 270,
      'wpickaxe' => 270,
      'woodenpick' => 270,
      'wpick' => 270,
      'woodenaxe' => 271,
      'waxe' => 271,
      'stonesword' => 272,
      'ssword' => 272,
      'stoneshovel' => 273,
      'sshovel' => 273,
      'stonepickaxe' => 274,
      'spickaxe' => 274,
      'stonepick' => 274,
      'spick' => 274,
      'stoneaxe' => 275,
      'saxe' => 275,
      'diamondsword' => 276,
      'dsword' => 276,
      'diamondshovel' => 277,
      'dshovel' => 277,
      'diamondpickaxe' => 278,
      'dpickaxe' => 278,
      'diamondpick' => 278,
      'dpick' => 278,
      'diamondaxe' => 279,
      'daxe' => 279,
      'stick' => 280,
      'sticks' => 280,
      'bowl' => 281,
      'mushroomsoup' => 282,
      'goldsword' => 283,
      'gsword' => 283,
      'goldshovel' => 284,
      'gshovel' => 284,
      'goldpickaxe' => 285,
      'gpickaxe' => 285,
      'goldpick' => 285,
      'gpick' => 285,
      'goldaxe' => 286,
      'gaxe' => 286,
      'string' => 287,
      'feather' => 288,
      'gunpowder' => 289,
      'woodenhoe' => 290,
      'whoe' => 290,
      'stonehoe' => 291,
      'shoe' => 291,
      'ironhoe' => 292,
      'ihoe' => 292,
      'diamondhoe' => 293,
      'dhoe' => 293,
      'goldhoe' => 294,
      'ghoe' => 294,
      'seed' => 295,
      'seeds' => 295,
      'wheat' => 296,
      'bread' => 297,
      'leathercap' => 298,
      'lcap' => 298,
      'leathertunic' => 299,
      'ltunic' => 299,
      'leatherpants' => 300,
      'lpants' => 300,
      'leatherboots' => 301,
      'lboots' => 301,
      'chainhelmet' => 302,
      'chelmet' => 302,
      'chainchestplate' => 303,
      'cchestplate' => 303,
      'chainleggings' => 304,
      'cleggings' => 304,
      'chainboots' => 305,
      'cboots' => 305,
      'ihelmet' => 306,
      'ironhelmet' => 306,
      'ichestplate' => 307,
      'ironchestplate' => 307,
      'ileggings' => 308,
      'ironleggings' => 308,
      'iboots' => 309,
      'ironboots' => 309,
      'dhelmet' => 310,
      'diamondhelmet' => 310,
      'dchestplate' => 311,
      'diamondchestplate' => 311,
      'dleggings' => 312,
      'diamondleggings' => 312,
      'dboots' => 313,
      'diamondboots' => 313,
      'ghelmet' => 314,
      'goldhelmet' => 314,
      'gchestplate' => 315,
      'goldchestplate' => 315,
      'gleggings' => 316,
      'goldleggings' => 316,
      'gboots' => 317,
      'goldboots' => 317,
      'flint' => 318,
      'rawporkchop' => 319,
      'cookedporkchop' => 320,
      'porkchop' => 320,
      'painting' => 321,
      'paintings' => 321,
      'goldenapple' => 322,
      'sign' => 323,
      'woodendoor' => 324,
      'wdoor' => 324,
      'bucket' => 325,
      'waterbucket' => 326,
      'wbucket' => 326,
      'lavabucket' => 327,
      'lbucket' => 327,
      'minecart' => 328,
      'saddle' => 329,
      'irondoor' => 330,
      'idoor' => 330,
      'redstone' => 331,
      'snowball' => 332,
      'boat' => 333,
      'leather' => 334,
      'milk' => 335,
      'claybrick' => 336,
      'clay' => 337,
      'sugarcane' => 338,
      'paper' => 339,
      'book' => 340,
      'slimeball' => 341,
      'storageminecart' => 342,
      'sminecart' => 342,
      'poweredminecart' => 343,
      'pminecart' => 343,
      'egg' => 344,
      'compass' => 345,
      'fishingrod' => 346,
      'rod' => 346,
      'clock' => 347,
      'glowstonedust' => 348,
      'rawfish' => 349,
      'cookedfish' => 350,
      'fish' => 350,
      'dye' => 351,
      'bone' => 352,
      'bones' => 352,
      'sugar' => 353,
      'cake' => 354,
      'bed' => 355,
      'redstonerepeater' => 356,
      'repeater' => 356,
      'cookie' => 357,
      'cookies' => 357,
      'map' => 358,
      'golddisc' => 2256,
      'gdisc' => 2256,
      'greendisc' => 2257,
      'gdisc' => 2257,
    
      'dkevlar' => '# dhelmet:1
                    + dchestplate:1
                    + dleggings:1
                    + dboots:1',
    );
  // }

  public function __construct()
  {
    set_time_limit(0);
    clearstatcache();
    date_default_timezone_set("Europe/Berlin");
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
