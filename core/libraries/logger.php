<?php
/**
 * Created by PhpStorm.
 * User: sahara
 * Date: 2017/11/13
 * Time: 18:30
 */

class logger
{
    private static $log =   array();

    public static function record($log_name=false,$log_content)
    {
        if(!is_string($log_name)) return false;
        if(is_object($log_content) || is_array($log_content)){
            $log_content=json_encode($log_content);
        }
        $prefix="--".date('ymd His').": ";
        $suffix="\n";//for all
        if( !defined('_LOG_') ) {
            throw new Exception("//CONSTANT _LOG_ not defined to use logger.");
        }
        $file_path=_LOG_ .'/'.$log_name."-".date('Ymd').".log";
        $log_content=mb_convert_encoding( $log_content, 'UTF-8', 'UNICODE,UTF-8,GBK,GB2312,BIG5');
        self::$log[]=$log_content;
        file_put_contents($file_path,$prefix.$log_content.$suffix,FILE_APPEND);
        return true;
    }

    public static function History()
    {
        return self::$log;
    }

}