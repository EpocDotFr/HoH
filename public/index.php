<?php
require '../vendor/autoload.php';

require '../src/orm_init.php';

$app = new \Slim\Slim([
  'templates.path' => '../templates',
  'cookies.encrypt' => true,
  'cookies.secret_key' => 'h24èdjtèjf5gh*/knvb,-,n5j,nfhhjdrtzé',
  'log.enabled' => true,
  'debug' => true,
  'log.writer' => new \HoH\MonologSlim('app', [new \Monolog\Handler\StreamHandler('../logs/app.log', \Monolog\Logger::DEBUG)])
]);

$app->view(new \Slim\Views\Twig());
$app->view->parserOptions = [
  'charset' => 'utf-8',
  'cache' => realpath('../templates/cache'),
  'auto_reload' => true,
  'strict_variables' => false,
  'autoescape' => true
];

$app->view->parserExtensions = [
  new \Slim\Views\TwigExtension(),
  new \HoH\TwigExtension()
];

// Accueil (sélection et import de compte)
$app->get('/', function () use ($app) {
  $app->render('home.twig', [
    'accounts' => Model::factory('Account')->find_many(),
    'regions' => Model::factory('AccountRegion')->find_many()
  ]);
});

// Import de compte
$app->post('/account', function () use ($app) {
  $post_data = $app->request->post();
  
  $account_region = ORM::for_table('account_region')->find_one($post_data['account_region_id']);
  
  $return = [];

  try {
    ORM::get_db()->beginTransaction();
    
    if (\HoH\Models\Account::already_exists($post_data['battlenet_id'], $post_data['username'], $post_data['account_region_id'])) {
      throw new \Exception('Ce compte a déjà été importé.');
    }
    
    $D3API = new \HoH\D3API($account_region->slug, $post_data['account_region_id'], $post_data['username'], $post_data['battlenet_id']);
    
    $D3API->importProfile();

    $return = [
      'result' => 'success',
      'data' => [
        'message' => 'Compte importé avec succès ! La page va maintenant s\'actualiser.'
      ]
    ];

    ORM::get_db()->commit();
  } catch (\Exception $e) {
    $return = [
      'result' => 'failure',
      'data' => [
        'message' => $e->getMessage()
      ]
    ];

    ORM::get_db()->rollBack();
  }

  $app->response->headers->set('Content-Type', 'application/json');
  $app->response->setBody(json_encode($return));
});

// Détails d'un compte avec liste des héros
$app->get('/account/:account_id', function ($account_id) use ($app) {
  $account = \Model::factory('Account')->find_one($account_id);
  
  $app->render('account.twig', [
    'account' => $account
  ]);
});

// Actualisation d'un compte
$app->post('/account/:account_id', function ($account_id) use ($app) {
  $account = \Model::factory('Account')->find_one($account_id);

  $return = [];

  try {
    ORM::get_db()->beginTransaction();
    
    if (!empty($account->last_updated) and date('Y-m-d', strtotime($account->last_updated)) == date('Y-m-d')) {
      throw new \Exception('Vous avez déjà actualisé ce compte aujourd\'hui. Vous ne pouvez l\'actualiser qu\'une fois par jour.');
    }
    
    $D3API = new \HoH\D3API($account->region()->find_one()->slug, $account->account_region_id, $account->username, $account->battlenet_id);
    
    $result_message = $D3API->refreshAccount($account);

    $return = [
      'result' => 'success',
      'data' => [
        'message' => $result_message.'. La page va maintenant s\'actualiser.'
      ]
    ];

    ORM::get_db()->commit();
  } catch (\Exception $e) {
    $return = [
      'result' => 'failure',
      'data' => [
        'message' => $e->getMessage()
      ]
    ];

    ORM::get_db()->rollBack();
  }

  $app->response->headers->set('Content-Type', 'application/json');
  $app->response->setBody(json_encode($return));
});

// Suppression d'un compte
$app->delete('/account/:account_id', function ($account_id) use ($app) {
  $account = \Model::factory('Account')->find_one($account_id);

  try {
    ORM::get_db()->beginTransaction();

    $account->delete();
    
    $return = [
      'result' => 'success',
      'data' => [
        'message' => 'Compte supprimé. Vous allez être redirigé(e) vers l\'accueil.'
      ]
    ];

    ORM::get_db()->commit();
  } catch (\Exception $e) {
    $return = [
      'result' => 'failure',
      'data' => [
        'message' => $e->getMessage()
      ]
    ];

    ORM::get_db()->rollBack();
  }

  $app->response->headers->set('Content-Type', 'application/json');
  $app->response->setBody(json_encode($return));
});

// Détails d'un héros d'un compte
$app->get('/account/:account_id/hero/:hero_id', function ($account_id, $hero_id) use ($app) {
  $account = \Model::factory('Account')->find_one($account_id);
  $hero = \Model::factory('Hero')->find_one($hero_id);
  
  $app->render('hero.twig', [
    'account' => $account,
    'hero' => $hero
  ]);
});

// Actualisation d'un héros
$app->post('/account/:account_id/hero/:hero_id', function ($account_id, $hero_id) use ($app) {
  $account = \Model::factory('Account')->find_one($account_id);
  $hero = \Model::factory('Hero')->find_one($hero_id);

  $return = [];

  try {
    ORM::get_db()->beginTransaction();

    if (!empty($hero->last_updated) and date('Y-m-d', strtotime($hero->last_updated)) == date('Y-m-d')) {
      throw new \Exception('Vous avez déjà actualisé ce Héros aujourd\'hui. Vous ne pouvez l\'actualiser qu\'une fois par jour.');
    }

    $D3API = new \HoH\D3API($account->region()->find_one()->slug, $account->account_region_id, $account->username, $account->battlenet_id);

    $D3API->refreshHero($hero);

    $return = [
      'result' => 'success',
      'data' => [
        'message' => 'Héros actualisé. La page va maintenant s\'actualiser.'
      ]
    ];

    ORM::get_db()->commit();
  } catch (\Exception $e) {
    $return = [
      'result' => 'failure',
      'data' => [
        'message' => $e->getMessage()
      ]
    ];

    ORM::get_db()->rollBack();
  }
  
  $app->response->headers->set('Content-Type', 'application/json');
  $app->response->setBody(json_encode($return));
});

// Récupération de données pour graphiques héros vue globale
$app->get('/account/:account_id/hero/:hero_id/datatype/:datatype', function ($account_id, $hero_id, $datatype) use ($app) {
  $account = \Model::factory('Account')->find_one($account_id);
  $hero = \Model::factory('Hero')->find_one($hero_id);

  $return = [];
  
  try {
    $data = [];
    
    switch ($datatype) {
      case 'resists':
        foreach ($hero->history()->find_many() as $hero_history) {
          $timestamp = date_create($hero_history->timestamp)->getTimestamp() * 1000;

          $data[0][] = ['x' => $timestamp, 'y' => (int) $hero_history->physical_resist];
          $data[1][] = ['x' => $timestamp, 'y' => (int) $hero_history->fire_resist];
          $data[2][] = ['x' => $timestamp, 'y' => (int) $hero_history->cold_resist];
          $data[3][] = ['x' => $timestamp, 'y' => (int) $hero_history->lightning_resist];
          $data[4][] = ['x' => $timestamp, 'y' => (int) $hero_history->poison_resist];
          $data[5][] = ['x' => $timestamp, 'y' => (int) $hero_history->arcane_resist];
        }
      break;
      case 'life':
        foreach ($hero->history()->find_many() as $hero_history) {
          $timestamp = date_create($hero_history->timestamp)->getTimestamp() * 1000;

          $data[0][] = ['x' => $timestamp, 'y' => (int) $hero_history->life];
          $data[1][] = ['x' => $timestamp, 'y' => (int) $hero_history->life_per_kill];
          $data[2][] = ['x' => $timestamp, 'y' => (int) $hero_history->life_on_hit];
          $data[3][] = ['x' => $timestamp, 'y' => (float) $hero_history->healing];
        }
      break;
      case 'percentages':
        foreach ($hero->history()->find_many() as $hero_history) {
          $timestamp = date_create($hero_history->timestamp)->getTimestamp() * 1000;

          $data[0][] = ['x' => $timestamp, 'y' => (float) $hero_history->crit_damage];
          $data[1][] = ['x' => $timestamp, 'y' => (float) $hero_history->block_chance];
          $data[2][] = ['x' => $timestamp, 'y' => (float) $hero_history->damage_increase];
          $data[3][] = ['x' => $timestamp, 'y' => (float) $hero_history->crit_chance];
          $data[4][] = ['x' => $timestamp, 'y' => (float) $hero_history->gold_find];
          $data[5][] = ['x' => $timestamp, 'y' => (float) $hero_history->lifes_steal];
          $data[6][] = ['x' => $timestamp, 'y' => (float) $hero_history->magic_find];
        }
      break;
    }

    $return = [
      'result' => 'success',
      'data' => $data
    ];
  } catch (\Exception $e) {
    $return = [
      'result' => 'failure',
      'data' => [
        'message' => $e->getMessage()
      ]
    ];
  }

  $app->response->headers->set('Content-Type', 'application/json');
  $app->response->setBody(json_encode($return));
});

$app->error(function (\Exception $e) use ($app) {
  $app->render('error.twig', [
    'message' => $e->getMessage()
  ]);
});

$app->notFound(function () use ($app) {
  $app->render('404.twig');
});

$app->run();
