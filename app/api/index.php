<?php
/**
 * Created by PhpStorm.
 * User: sahara
 * Date: 2017/11/13
 * Time: 16:08
 */

define('SUB_APP_ROOT',__DIR__);
define('CURRENT_ROOT',__DIR__);
require_once dirname(__DIR__).'/app.ini.php';

if( !@include( (__DIR__).'/control/control.php') )
{
    exit('Control file not exist.');
}

RpcRouter::init();
RpcRouter::handle(array(
    'defaultClass'=>'indexControl',
    'defaultMethod'=>'indexOp',
    'APP_NAME'=>'WEB'
));