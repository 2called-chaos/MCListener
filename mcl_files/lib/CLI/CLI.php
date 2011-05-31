<?php

require_once('Color.php');

class Core_CLI
{
  protected $_stdin = null;
  protected $_overwrit = null;
  public $runtime = null;

  public function __construct()
  {
    $this->_stdin = fopen("php://stdin", "r");
    // $this->runtime = new Core_CLI_Runtime; 
  }

  public function readUserInput($trim = true)
  {
    $input = fread($this->_stdin, 1024);

    // trim input
    if ($trim) {
      $input = trim($input);
    }

    return $input;
  }
  
  public function overwrit($text = '', $format = true)
  {
    if($format) {
      $stext = Core_CLI_Color::convert($text);
    } else {
      $stext = $text;
    }
    
    // remove already printed text
    if(!is_null($this->_overwrit)) {
      echo str_pad('', strlen($this->_overwrit), chr(8), STR_PAD_LEFT);
    }
    
    $this->send($stext, false);
    
    if($format) {
      $this->_overwrit = str_replace("%%", "%", preg_replace('#(%[\w]{1})#is', '', $text));
    } else {
      $this->_overwrit = $stext;
    }
  }

  public function readVar($display_text)
  {
    $response = null;
    
    while($response == null) {
      echo Core_CLI_Color::convert('%y' . $display_text . ':%n %b');
      $response = $this->rc();
      echo Core_CLI_Color::convert("%n");
    }
    
    return $response;
  }
  
  public function abort($message = "Script aborted!")
  {
    $this->sendf("\n%r" . $message . "%n");
    
    die;
  }
  
  public function forceReadVar($display_text, $possible_values)
  {
    $result = false;
    
    while(!($result = self::isUnique($result, $possible_values))) {
      $result = $this->readVar($display_text . '%y (' . implode("/", $possible_values) . ')%n');
    }
    
    $this->sendf(" %g=> \"" . $result . "\" selected%n");
    
    return $result;
  }  
  
  public static function isUnique($result, $possible_values)
  {
    $hits = array();
    
    foreach($possible_values as $val) {
      if(substr($val, 0, strlen($result)) == $result) {
        $hits[] = $val;
      }
    }
    
    return count($hits) == 1 || in_array($result, $possible_values) ? $hits[0] : false;
  }

  public function rc($trim = true)
  {
    return $this->readUserInput($trim);
  }

  public function error($text) {
    echo "\n" . $text . "\n";
  }

  public function errorf($text) {
    echo "\n" . Core_CLI_Color::convert($text) . "\n";
  }

  public function send($text, $break = true)
  {
    echo $text;
    if($break) {
      echo "\n";
    }
  }
  public function sendf($text, $break = true)
  {
    echo Core_CLI_Color::convert($text);
    if($break) {
      echo "\n";
    }
  }
}