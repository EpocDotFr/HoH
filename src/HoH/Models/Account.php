<?php
namespace HoH\Models;

class Account extends \Model {
  public static $_table = 'account';
  
  public function heros() {
    return $this->has_many('Hero');
  }
  
  public function region() {
    return $this->belongs_to('AccountRegion');
  }
  
  public static function already_exists($battlenet_id, $username, $account_region_id) {
    return \Model::factory('Account')
      ->where_equal('battlenet_id', $battlenet_id)
      ->where_equal('username', $username)
      ->where_equal('account_region_id', $account_region_id)
      ->count() > 0;
  }
  
  public function delete() {
    $heros = $this->heros()->find_many();

    foreach ($heros as $hero) {
      $hero->history()->delete_many();
      $hero->delete();
    }
    
    parent::delete();
  }
} 