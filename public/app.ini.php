<?php
/**
 * Created by PhpStorm.
 * User: sahara
 * Date: 2017/11/13
 * Time: 15:29
 */

error_reporting(E_ALL  ^ E_NOTICE);

require_once __DIR__.'/../app.php';

define('APP_PATH',__DIR__);

define('_DATA_PATH_',APP_PATH.'/data');

define('_LOG_',_DATA_PATH_.'/log');

if( !@include( APP_PATH.'/data/config/config.common.php') ){
    exit('Config file not found.');
}
GLOBAL $config;

require_once BASE_CORE_PATH.'/function/function.php';
require_once __DIR__.'/../autoload/autoload.php'; // defile APP_PATH before require this file
