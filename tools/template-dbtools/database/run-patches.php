<?php

if (file_exists(dirname(__DIR__) . '/vendor/jagermesh/bright/Bright.php')) {
  require_once(dirname(__DIR__) . '/vendor/jagermesh/bright/Bright.php');
} else {
  require_once(dirname(__DIR__) . '/bright/Bright.php');
}

if (!br()->isConsoleMode()) { br()->panic('Console mode only'); }
$handle = br()->OS()->lockIfRunning(br()->getScriptPath());

$dataBaseManager = new \Bright\BrDataBaseManager();
$dataBaseManager->runMigrationCommand(__FILE__);
