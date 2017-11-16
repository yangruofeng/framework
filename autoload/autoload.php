<?php

define('START_TIME', microtime(true));

/*
|--------------------------------------------------------------------------
| Register The Composer Auto Loader
|--------------------------------------------------------------------------
|
| Composer provides a convenient, automatically generated class loader
| for our application. We just need to utilize it! We'll require it
| into the script here so we do not have to manually load any of
| our application's PHP classes. It just feels great to relax.
|
*/
//require __DIR__.'/../vendor/autoload.php';

// for simple framework
function includeFile($file)
{
    include $file;
}

class ClassLoader
{
    public  function __construct()
    {
    }

    public  function register($prepend = false)
    {
        spl_autoload_register(array($this,'loadClass'),true,$prepend);
    }

    public  function unregister()
    {
        spl_autoload_unregister(array($this,'loadClass'));
    }

    public function loadClass($class)
    {
        $file = $this->findFileWithExtension($class);
        if( $file ){
            includeFile($file);
        }
    }

    protected function findFileWithExtension($class,$ext='.php')
    {

        if( strtoupper( substr($class,-strlen('class')) ) == 'CLASS' ){

            $file = APP_PATH.'/class/'.substr($class,0,-strlen('class')).'.class'.$ext;
            if( file_exists($file)){
                return $file;
            }

            $file = ROOT_PATH.'/class/'.substr($class,0,-strlen('class')).'.class'.$ext;
            if( file_exists($file)){
                return $file;
            }
        }

        if( strtoupper( substr($class,-strlen('model')) ) == 'MODEL' ){
            $file = APP_PATH.'/model/'.substr($class,0,-strlen('model')).'.model'.$ext;
            if( file_exists($file)){
                return $file;
            }

            $file = ROOT_PATH.'/model/'.substr($class,0,-strlen('model')).'.model'.$ext;
            if( file_exists($file)){
                return $file;
            }
        }

        if( strtoupper( substr($class,-strlen('control')) ) == 'CONTROL' ){
            $file = APP_PATH.'/control/'.substr($class,0,-strlen('control')).$ext;
            if( file_exists($file)){
                return $file;
            }

            $file = SUB_APP_ROOT.'/control/'.substr($class,0,-strlen('control')).$ext;
            if( file_exists($file)){
                return $file;
            }

        }

        $class_map = array(
            BASE_CORE_PATH,
            BASE_CORE_PATH.'/libraries',
            BASE_EXTERNAL_PATH,
            BASE_CLASS_PATH,
            APP_PATH.'/class',
            APP_PATH.'/model',
            APP_PATH.'/control',
            SUB_APP_ROOT.'/control'
        );
        foreach( $class_map as $path ){
            $file = $path.'/'.$class.$ext;
            if( file_exists($file)){
                return $file;
            }
        }

        return false;
    }
}

$ClassLoader = new ClassLoader();
$ClassLoader->register();
