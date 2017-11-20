<?php
/**
 * Created by PhpStorm.
 * User: sahara
 * Date: 2017/11/20
 * Time: 13:56
 */
class language
{
    protected static $instance = null;
    protected static $language_file = array();

    public static $language_content = array();

    public static function getInstance()
    {
        if( self::$instance === null || !(self::$instance instanceof language) ){
            self::$instance = new language();
        }
        return self::$instance;
    }

    public static function getCurrentLang()
    {
        $lan = 'en';
        if( $_GET('lang') ){
            $lan = $_GET('lang');
        }elseif( $_COOKIE['lang'] ){
            $lan = $_COOKIE['lang'];
        }else{

            $lan = self::getAcceptLanguage();
            if( !$lan ){
                $lan = $GLOBALS['config']['default_language'];
            }
            if( !$lan ){
                $lan = 'en';
            }
        }

        return $lan;
    }

    public static function readFile($files)
    {
        if( is_array($files) ){
            self::$language_file = array_merge(self::$language_file,$files);
        }
        if( is_string($files) ){
            $files = str_replace('，',',',$files);
            $files = explode(',',trim($files,','));
            self::$language_file = array_merge(self::$language_file,$files);
        }
        $lan = self::getCurrentLang();
        foreach( self::$language_file as $file ){

            // app->language
            $path = APP_PATH.'/language/'.$lan.'/'.$file.'.php';
            $lang = array();  // 每次读取前置空
            if( file_exists($path)){
                require_once $path;
                if( !empty($lang) && is_array($lang) ){
                    self::$language_content = array_merge(self::$language_content,$lang);
                }
            }
            unset($lang);  // 只读文件lang

            // sub-app->language
            $path = CURRENT_ROOT.'/language/'.$lan.'/'.$file.'.php';
            $lang = array();  // 每次读取前置空
            if( file_exists($path)){
                require_once $path;
                if( !empty($lang) && is_array($lang) ){
                    self::$language_content = array_merge(self::$language_content,$lang);
                }
            }
            unset($lang);
        }

        return true;
    }

    public static function getContent()
    {
        return self::$language_content;
    }

    public static function getKey($key)
    {
        $content = self::getContent();
        if( isset($content[$key])){
            return $content[$key];
        }
        return $key;
    }

    public static function setKey($key,$value)
    {
        self::$language_content[$key] = $value;
    }

    private static function getAcceptLanguage(){
        $langs = array();

        if ( isset($_SERVER['HTTP_ACCEPT_LANGUAGE']) ) {
            preg_match_all('/([a-z]{1,8}(-[a-z]{1,8})?)\s*(;\s*q\s*=\s*(1|0\.[0-9]+))?/i', $_SERVER['HTTP_ACCEPT_LANGUAGE'], $lang_parse);
            if (count($lang_parse[1])) {
                $langs = array_combine($lang_parse[1], $lang_parse[4]);
                foreach ($langs as $lang => $val) {
                    if ($val === '') $langs[$lang] = 1;
                }
                arsort($langs, SORT_NUMERIC);
            }
        }
        foreach ($langs as $lang => $val) {
            $lang=strtolower($lang);
            if(array_key_exists($lang,$GLOBALS['config']['languages'])){
                return $lang;
            }
        }
        return '';
    }
}