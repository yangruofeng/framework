<?php
/**
 * Created by PhpStorm.
 * User: sahara
 * Date: 2017/11/13
 * Time: 15:29
 */

error_reporting(E_ALL  ^ E_NOTICE);

require_once __DIR__.'/../app.php';
require_once BASE_CORE_PATH.'/function/function.php';


define('APP_PATH',__DIR__);

define('APP_RESOURCE_PATH',APP_PATH.'/resource');

define('_DATA_PATH_',APP_PATH.'/data');

define('_LOG_',_DATA_PATH_.'/log');

define('_DATA_SCHEMA_',_DATA_PATH_.'/schema');

define('_UPLOAD_PATH_',_DATA_PATH_.'/upload');

define('APP_MODEL',APP_PATH.'/model');

if( !is_dir(_DATA_PATH_) ){
    mkdir(_DATA_PATH_,0755);
}
if( !is_dir(_LOG_) ){
    mkdir(_LOG_,0755);
}
if( !is_dir(_DATA_SCHEMA_) ){
    mkdir(_DATA_SCHEMA_,0755);
}
if( !is_dir(_UPLOAD_PATH_) ){
    mkdir(_UPLOAD_PATH_,0755);
}
if( !is_dir(_DATA_PATH_.'/session') ){
    mkdir(_DATA_PATH_.'/session',0755);
}

require_once __DIR__.'/../autoload/autoload.php'; // defile APP_PATH before require this file


if( !@include( APP_PATH.'/data/config/config.common.php') ){
    exit('Config file not found.');
}
$config_switch = @include(ROOT_PATH.'/config.global.setting.temp');
if( !$config_switch ){
    $config_switch = 'config.local';
}
$config_switch .= '.php';
if( !@include( _DATA_PATH_.'/config/'.$config_switch) ){
    exit(_DATA_PATH_.'/config/'.$config_switch.' not exist.');
}
GLOBAL $config;

require_once(BASE_CORE_PATH . "/libraries/ormYo.php");
require_once(BASE_CORE_PATH . "/libraries/Yo.php");
require_once BASE_CORE_PATH.'/libraries/defineEnum.php';

$dsn = $GLOBALS['config']['db_conf'];
ormYo::setup($dsn);
ormYo::$default_db_key = "db_default";
ormYo::$lang_current = "en";
ormYo::$freez = !$GLOBALS['config']['debug'];//==true;//是否冻结
ormYo::$log_path = _LOG_;//日志路径
//ormYo::$IDField="uid";//表的自增列
ormYo::$schema_path = _DATA_SCHEMA_ . "/";//datasource保存路径
//ormYo::$lang_a = array();


define('GLOBAL_RESOURCE_URL',$config['global_resource_url']);
define('API_ENTRY_URL',$config['api_entry_url']);
