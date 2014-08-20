<?php

define('WOMBAT_PUBLIC_ROOT', __DIR__);
define('WOMBAT_BASE_URL', 'http://' . $_SERVER['SERVER_NAME']);
define('WOMBAT_APP_ROOT', __DIR__.'/../app');
define('WOMBAT_SRC_ROOT', __DIR__.'/../src');

define('WOMBAT_VIEW_ROOT', WOMBAT_SRC_ROOT.'/Sprout/Wombat/View');

require_once __DIR__.'/../vendor/autoload.php';

$app = new Silex\Application();

require WOMBAT_APP_ROOT.'/config_dev.php';
require WOMBAT_SRC_ROOT.'/app.php';
require WOMBAT_SRC_ROOT.'/routes.php';

$app->run();