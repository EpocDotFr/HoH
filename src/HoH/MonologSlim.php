<?php
namespace HoH;

use Monolog\Logger;

class MonologSlim extends Logger {
  function __construct($name, $handlers = array(), $processors = array()) {
    parent::__construct($name, $handlers, $processors);
  }

  public function write($message, $level) {
    $this->log($level * 100, $message);
  }
} 