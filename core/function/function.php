<?php
/**
 * Created by PhpStorm.
 * User: sahara
 * Date: 2017/11/13
 * Time: 15:10
 */

function C($key, $value = null)
{
    if ($value) {
        $setting_config[$key] = $value;
        return $value;
    }
    $conf = $GLOBALS['setting_config'];
    if ($key) {
        if (array_key_exists($key, $conf)) {
            return $conf[$key];
        } else {
            throw new Exception("ConfigError:$key not found");
        }
    }
    throw new Exception("ConfigError:$key not found");
}


function obj2array($obj,$is_deep=true){
    $vars = is_object($obj) ? get_object_vars($obj) : $obj;
    foreach ($vars as $k => $v){
        if(is_numeric(stripos($k,'parent_obj'))) continue;
        $new_v = $is_deep&&(is_array($v) || is_object($v)) ? obj2array($v) : $v;
        $arr[$k] = $new_v;
    }
    return $arr;
}
function array2obj($arr, $obj,$create=false) {
    $vars=get_class_vars(get_class($obj));
    foreach($vars as $pn=>$pv){
        if (is_array($pv)) continue;
        $obj->$pn=$arr[$pn];
    }
    if($create){
        foreach($arr as $k=>$v){
            if(!is_numeric($k)){
                $obj->$k=$v;
            }
        }
    }
}