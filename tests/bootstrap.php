<?php

$database = dirname(__DIR__).'/database/testing.sqlite';

if (! file_exists($database)) {
    touch($database);
}

putenv("DB_DATABASE={$database}");
$_ENV['DB_DATABASE'] = $database;
$_SERVER['DB_DATABASE'] = $database;

require dirname(__DIR__).'/vendor/autoload.php';
