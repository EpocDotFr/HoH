<?php
namespace HoH\Models;

class Hero extends \Model {
  public static $_table = 'hero';

  public function account() {
    return $this->belongs_to('Account');
  }
  
  public function hero_class() {
    return $this->belongs_to('HeroClass');
  }
  
  public function history() {
    return $this->has_many('HeroHistory');
  }
} 