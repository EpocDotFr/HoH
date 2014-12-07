<?php
namespace HoH\Models;

class HeroHistory extends \Model {
  public static $_table = 'hero_history';
  
  public function hero() {
    return $this->belongs_to('Hero');
  }
} 