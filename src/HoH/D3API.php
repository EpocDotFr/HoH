<?php
namespace HoH;

class D3API {
  private $endpoint, $username, $id, $region_id;
  
  private $apikey = 'YourAPIKey';
  private $locale = 'fr_FR';
  
  function __construct($region, $region_id, $username, $id) {
    $this->endpoint = sprintf('https://%s.api.battle.net/d3/', $region);
    
    $this->username = $username;
    $this->id = $id;
    $this->region_id = $region_id;
  }
  
  private function call($endpoint) {
    $endpoint = $endpoint.sprintf('?locale=%s&apikey=%s', $this->locale, $this->apikey);

    $headers = [
      'Accept' => 'application/json'
    ];
    
    $options = [
      'useragent' => 'History of Heroes Requests/'.\Requests::VERSION
    ];
    
    $response = \Requests::get($endpoint, $headers, $options);

    if (!$response->success) {
      $response->throw_for_status(true);
    }

    $response_json = json_decode($response->body);

    if (isset($response_json->code)) {
      switch ($response_json->code) {
        case 'NOTFOUND':
          $message = 'Ce compte Battle.net n\'existe pas.';
        break;
        default:
          $message = $response_json->reason.' ('.$response_json->code.')';
      }
      
      throw new \Exception($message);
    }

    return $response_json;
  }
  
  public function importProfile() {
    $endpoint = $this->endpoint.sprintf('profile/%s-%d/', $this->username, $this->id);
    
    $response = $this->call($endpoint);
    
    $classes = [];
    
    $hero_classes = \ORM::for_table('hero_class')->find_many();
    
    foreach ($hero_classes as $hero_class) {
      $classes[$hero_class->slug] = $hero_class->id;
    }
    
    $account = \Model::factory('Account')->create([
      'battlenet_id' => $this->id,
      'username' => $this->username,
      'last_updated' => date('Y-m-d H:i:s'),
      'account_region_id' => $this->region_id
    ]);
    
    $account->save();

    foreach ($response->heroes as $hero) {
      \Model::factory('Hero')->create([
        'battlenet_id' => $hero->id,
        'name' => $hero->name,
        'gender' => $hero->gender,
        'lastly_played' => $response->lastHeroPlayed == $hero->id ? 1 : 0,
        'seasonal' => $hero->seasonal,
        'hardcore' => $hero->hardcore,
        'account_id' => $account->id,
        'hero_class_id' => $classes[$hero->class]
      ])->save();
    }
  }
  
  public function refreshAccount($account) {
    $message = 'Le compte a été actualisé';
    
    $endpoint = $this->endpoint.sprintf('profile/%s-%d/', $this->username, $this->id);
    
    $classes = [];

    $hero_classes = \ORM::for_table('hero_class')->find_many();

    foreach ($hero_classes as $hero_class) {
      $classes[$hero_class->slug] = $hero_class->id;
    }

    $response = $this->call($endpoint);
    
    $bnet_last_updated = new \DateTime();
    $bnet_last_updated->setTimestamp($response->lastUpdated);
    $hoh_last_updated = new \DateTime($account->last_updated);
    
    if (!empty($account->last_updated) and $bnet_last_updated <= $hoh_last_updated) {
      throw new \Exception('Aucune actualisation n\'est nécéssaire, vous n\'avez pas joué depuis la dernière actualisation.');
    }
    
    $heroes_deleted = 0;
    $heros_created = 0;

    $battlenet_heros_ids = [];
    
    foreach ($response->heroes as $bnet_hero) {
      $battlenet_heros_ids[] = $bnet_hero->id;
    }
    
    foreach ($account->heros()->find_many() as $hoh_hero) {
      if (!in_array($hoh_hero->battlenet_id, $battlenet_heros_ids)) {
        $hoh_hero->delete();
        $heroes_deleted++;
        continue;
      }
      
      foreach ($response->heroes as $bnet_hero) {
        if ($bnet_hero->id != $hoh_hero->battlenet_id) {
          continue;
        }

        $hoh_hero->lastly_played = $response->lastHeroPlayed == $hoh_hero->battlenet_id ? 1 : 0;

        $hoh_hero->save();
      }
    }

    foreach ($response->heroes as $bnet_hero) {
      if ($account->heros()->where_equal('battlenet_id', $bnet_hero->id)->count() == 1) {
        continue;
      }

      \Model::factory('Hero')->create([
        'battlenet_id' => $bnet_hero->id,
        'name' => $bnet_hero->name,
        'gender' => $bnet_hero->gender,
        'lastly_played' => $response->lastHeroPlayed == $bnet_hero->id ? 1 : 0,
        'account_id' => $account->id,
        'hero_class_id' => $classes[$bnet_hero->class]
      ])->save();

      $heros_created++;
    }

    if ($heroes_deleted > 0) {
      $message .= '. '.$heroes_deleted.' Héros a(ont) été supprimé(s)';
    }

    if ($heros_created > 0) {
      $message .= '. '.$heros_created.' Héros a(ont) été créé(s)';
    }

    $account->last_updated = date('Y-m-d H:i:s');
    $account->save();
    
    return $message;
  }
  
  public function refreshHero($hero) {
    $endpoint = $this->endpoint.sprintf('profile/%s-%d/hero/%d', $this->username, $this->id, $hero->battlenet_id);

    $response = $this->call($endpoint);

    $bnet_last_updated = new \DateTime();
    $bnet_last_updated->setTimestamp($response->{'last-updated'});
    $hoh_last_updated = new \DateTime($hero->last_updated);

    if (!empty($hero->last_updated) and $bnet_last_updated <= $hoh_last_updated) {
      throw new \Exception('Aucune actualisation n\'est nécéssaire, vous n\'avez pas joué avec ce Héros depuis la dernière actualisation.');
    }
    
    $HeroHistory = \Model::factory('HeroHistory')->create([
      'timestamp' => date('Y-m-d H:i:s'),
      'life' => $response->stats->life,
      'damage' => $response->stats->damage,
      'toughness' => $response->stats->toughness,
      'healing' => $response->stats->healing,
      'attack_speed' => $response->stats->attackSpeed,
      'armor' => $response->stats->armor,
      'strength' => $response->stats->strength,
      'dexterity' => $response->stats->dexterity,
      'vitality' => $response->stats->vitality,
      'intelligence' => $response->stats->intelligence,
      'physical_resist' => $response->stats->physicalResist,
      'fire_resist' => $response->stats->fireResist,
      'cold_resist' => $response->stats->coldResist,
      'lightning_resist' => $response->stats->lightningResist,
      'poison_resist' => $response->stats->poisonResist,
      'arcane_resist' => $response->stats->arcaneResist,
      'crit_damage' => $response->stats->critDamage,
      'block_chance' => $response->stats->blockChance,
      'block_amount_min' => $response->stats->blockAmountMin,
      'block_amount_max' => $response->stats->blockAmountMax,
      'damage_increase' => $response->stats->damageIncrease,
      'crit_chance' => $response->stats->critChance,
      'damage_reduction' => $response->stats->damageReduction,
      'thorns' => $response->stats->thorns,
      'lifes_steal' => $response->stats->lifeSteal,
      'life_per_kill' => $response->stats->lifePerKill,
      'gold_find' => $response->stats->goldFind,
      'magic_find' => $response->stats->magicFind,
      'life_on_hit' => $response->stats->lifeOnHit,
      'primary_resource' => $response->stats->primaryResource,
      'secondary_resource' => $response->stats->secondaryResource,
      'level' => $response->level,
      'kills_elites' => $response->kills->elites,
      'paragon_level' => $response->paragonLevel,
      'hero_id' => $hero->id
    ]);
    
    $HeroHistory->save();

    $hero->last_updated = date('Y-m-d H:i:s');
    $hero->save();
  }
} 
