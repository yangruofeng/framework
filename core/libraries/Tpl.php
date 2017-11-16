<?php
/**
 * Created by PhpStorm.
 * User: sahara
 * Date: 2017/11/15
 * Time: 13:11
 */

/** 单例
 * Class Tpl
 */
final class Tpl
{
    private static $instance = null;

    private static $layout = null;
    private static $dir = 'default';
    private static $layout_dir = 'layout';
    private static $output = array();

    private function __construct()
    {

    }

    public static function getInstance()
    {
        if( self::$instance === null || !(self::$instance instanceof Tpl) ){
            self::$instance = new Tpl();
        }
        return self::$instance;
    }

    public static function setDir($dir)
    {
        self::$dir = $dir;
    }

    public static function setLayout($layout)
    {
        self::$layout = $layout;
    }

    public static function output($name,$value)
    {
        self::getInstance();
        self::$output[$name] = $value;
    }

    public static function show($page,$layout='',$dir='')
    {
        self::getInstance();
        !defined('CURRENT_ROOT') && exit('Did not define CURRENT_ROOT path.');
        $layout_file = '';
        $page_dir = '';

        if( self::$layout ){
           $layout_file = self::$layout;
        }
        if( $layout ){
            $layout_file = $layout;
        }

        if( self::$dir ){
            $page_dir = self::$dir;
        }
        if( $dir ){
            $page_dir = $dir;
        }

        if( $layout_file ){
            $layout_file = CURRENT_ROOT.'/views/'.self::$layout_dir.'/'.$layout_file.'.php';
        }

        $page_file = CURRENT_ROOT.'/views/'.$page_dir.'/'.$page.'.php';

        if( file_exists($page_file) ){

            @header('Content-type: text/html; charset=uft-8');
            if( $layout_file ){
                if( !file_exists($layout_file) ){
                    exit('Layout file'.$layout_file.' not exist.');
                }
                $tpl_output = self::$output;
                $tpl_file = $page_file;
                @include_once $layout_file;

            }else{
                @include_once $page_file;
            }
        }else{
            exit('Tpl file '.$page_file.' not exist.');
        }
        exit();
    }

    /**
     * 显示页面Trace信息
     *
     * @return array
     */
    public static function showTrace(){
        $trace = array();
        //当前页面
        $trace['debug_current_page'] =  $_SERVER['REQUEST_URI'].'<br>';
        //请求时间
        $trace['debug_request_time'] =  date('Y-m-d H:i:s',$_SERVER['REQUEST_TIME']).'<br>';
        //系统运行时间
        $query_time = number_format((microtime(true)-StartTime),3).'s';
        $trace['debug_execution_time'] = $query_time.'<br>';
        //内存
        $trace['debug_memory_consumption'] = number_format(memory_get_usage()/1024/1024,2).'MB'.'<br>';
        //请求方法
        $trace['debug_request_method'] = $_SERVER['REQUEST_METHOD'].'<br>';
        //通信协议
        $trace['debug_communication_protocol'] = $_SERVER['SERVER_PROTOCOL'].'<br>';
        //用户代理
        $trace['debug_user_agent'] = $_SERVER['HTTP_USER_AGENT'].'<br>';
        //会话ID
        $trace['debug_session_id'] = session_id().'<br>';
        //执行日志
        //$log    =   logger::History();
        //$trace['debug_logging']  =$log?implode('<br/>',$log):'';
        //$trace['debug_logging'] = "<div style=' background-color: #222222; padding: 10px; color: yellow'>".$trace['debug_logging'].'</div><br>';
        //文件加载
        $files =  get_included_files();
        $trace['debug_load_files'] = count($files).str_replace("\n",'<br/>',substr(substr(print_r($files,true),7),0,-2)).'<br>';
        print_r($trace);
        return $trace;
    }

}