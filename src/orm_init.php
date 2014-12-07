<?php
ORM::configure([
  'connection_string' => 'sqlite:../storage/db.sqlite',
  'return_result_sets' => true,
  'error_mode' => PDO::ERRMODE_EXCEPTION,
  'driver_options' => [PDO::MYSQL_ATTR_INIT_COMMAND => 'SET NAMES UTF8'],
  'id_column' => 'id',
  'logging' => true
]);

$log_idiorm = new \Monolog\Logger('db', [new \Monolog\Handler\StreamHandler('../logs/db.log', \Monolog\Logger::DEBUG)]);

ORM::configure('logger', function($log_string, $query_time) use ($log_idiorm) {
  $log_idiorm->debug($query_time.' - '.$log_string);
});

Model::$auto_prefix_models = '\\HoH\Models\\';