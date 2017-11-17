<?php
/**
 * Created by PhpStorm.
 * User: sahara
 * Date: 2017/11/13
 * Time: 15:10
 */

function global_error_handler($err_no,$err_str,$err_file,$err_line)
{
    $return = array(
        'errno' => $err_no,
        'errstr' => $err_str,
        'file' => $err_file,
        'line' => $err_line
    );
    if( $err_no != E_NOTICE && $err_no != E_USER_NOTICE ){
        echo json_encode($return);
    }
    return $return;
}

set_error_handler('global_error_handler');

function global_exception_handler(Exception $e)
{
    echo json_encode(array(
        'errno' => $e->getCode(),
        'errstr' => $e->getMessage(),
        'file' => $e->getFile(),
        'line' => $e->getLine(),
        'trace' => $e->getTraceAsString()
    ));
}

set_exception_handler('global_exception_handler');

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

function getConf($key)
{
    $conf = $GLOBALS['config'];
    if( $key ){
        if( array_key_exists($key,$conf) ){
            return $conf[$key];
        }
        return null;
    }
    return null;
}


function obj2array($obj,$is_deep=true){

    $obj = is_object($obj)?get_object_vars($obj): $obj;
    if( is_array($obj) ){
        foreach( $obj as $k=> $v ){
            $obj[$k] = obj2array($v);
        }
    }
    return $obj;

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

function array_to_object($arr)
{
    $arr = (array) $arr;
    foreach( $arr as $k=>$v ){
        if( gettype($v) == 'array' || gettype($v) == 'object' ){
            $arr[$k] = (object) array_to_object($v);
        }
    }
    return (object) $arr;
}

function my_json_decode($json,$flag=true)
{
    return json_decode($json,$flag);
}

function adjust_timezone(){
    $SERVER_TIMEZONE=getConf('SERVER_TIMEZONE');
    if($SERVER_TIMEZONE==''){
        throw new Exception("SERVER_TIMEZONE must be config");
    }else{
        $ini_get_date_timezone=ini_get("date.timezone");
        if($SERVER_TIMEZONE!= $ini_get_date_timezone ){
            @ini_set("date.timezone",$SERVER_TIMEZONE);
            //date_default_timezone_set($SERVER_TIMEZONE);
        }
    }
}

function debug(){
    $debug=getConf("debug");
    if(!$debug) return false;
    $log_type = '';
    $prefix = '';
    $log_content = '';
    $arr=func_get_args();
    if(!count($arr)) return false;
    if(count($arr)==1) $log_content=$arr[0];
    if(count($arr)==2) {$log_type=$arr[0];$log_content=$arr[1];}
    if(count($arr)==3) {$log_type=$arr[0];$log_content=$arr[1];$prefix=$arr[2];}

    $trace = debug_backtrace(false);
    $class = $trace[1]['class'];
    $function = $trace[1]['function'];

    if(!$log_type){
        $log_type=$class."-".$function;
    }
    if($prefix!="")
        $prefix.=":";
    if (is_array($log_content) || is_object($log_content))
        $log_content = $prefix.json_encode($log_content);
    else{
        $log_content = $prefix.$log_content;
    }
    return logger::record($log_type,$log_content);
}

// 判断是否手机浏览器
function is_mobile_brower()
{

    if( isset($_SERVER['HTTP_X_WAP_PROFILE']) ){
        return true;
    }

    if( isset($_SERVER['HTTP_VIA']) ){

        return stristr($_SERVER['HTTP_VIA'], "wap") ? true : false;
    }

    if( isset( $_SERVER['HTTP_USER_AGENT'] ) ){

        $user_agent = $_SERVER['HTTP_USER_AGENT'];

        if(preg_match('/(android|bb\d+|meego).+mobile|avantgo|bada\/|blackberry|blazer|compal|elaine|fennec|hiptop|iemobile|ip(hone|od)|iris|kindle|lge |maemo|midp|mmp|mobile.+firefox|netfront|opera m(ob|in)i|palm( os)?|phone|p(ixi|re)\/|plucker|pocket|psp|series(4|6)0|symbian|treo|up\.(browser|link)|vodafone|wap|windows ce|xda|xiino|MicroMessenger/i',$user_agent)
            ||preg_match('/1207|6310|6590|3gso|4thp|50[1-6]i|770s|802s|a wa|abac|ac(er|oo|s\-)|ai(ko|rn)|al(av|ca|co)|amoi|an(ex|ny|yw)|aptu|ar(ch|go)|as(te|us)|attw|au(di|\-m|r |s )|avan|be(ck|ll|nq)|bi(lb|rd)|bl(ac|az)|br(e|v)w|bumb|bw\-(n|u)|c55\/|capi|ccwa|cdm\-|cell|chtm|cldc|cmd\-|co(mp|nd)|craw|da(it|ll|ng)|dbte|dc\-s|devi|dica|dmob|do(c|p)o|ds(12|\-d)|el(49|ai)|em(l2|ul)|er(ic|k0)|esl8|ez([4-7]0|os|wa|ze)|fetc|fly(\-|_)|g1 u|g560|gene|gf\-5|g\-mo|go(\.w|od)|gr(ad|un)|haie|hcit|hd\-(m|p|t)|hei\-|hi(pt|ta)|hp( i|ip)|hs\-c|ht(c(\-| |_|a|g|p|s|t)|tp)|hu(aw|tc)|i\-(20|go|ma)|i230|iac( |\-|\/)|ibro|idea|ig01|ikom|im1k|inno|ipaq|iris|ja(t|v)a|jbro|jemu|jigs|kddi|keji|kgt( |\/)|klon|kpt |kwc\-|kyo(c|k)|le(no|xi)|lg( g|\/(k|l|u)|50|54|\-[a-w])|libw|lynx|m1\-w|m3ga|m50\/|ma(te|ui|xo)|mc(01|21|ca)|m\-cr|me(rc|ri)|mi(o8|oa|ts)|mmef|mo(01|02|bi|de|do|t(\-| |o|v)|zz)|mt(50|p1|v )|mwbp|mywa|n10[0-2]|n20[2-3]|n30(0|2)|n50(0|2|5)|n7(0(0|1)|10)|ne((c|m)\-|on|tf|wf|wg|wt)|nok(6|i)|nzph|o2im|op(ti|wv)|oran|owg1|p800|pan(a|d|t)|pdxg|pg(13|\-([1-8]|c))|phil|pire|pl(ay|uc)|pn\-2|po(ck|rt|se)|prox|psio|pt\-g|qa\-a|qc(07|12|21|32|60|\-[2-7]|i\-)|qtek|r380|r600|raks|rim9|ro(ve|zo)|s55\/|sa(ge|ma|mm|ms|ny|va)|sc(01|h\-|oo|p\-)|sdk\/|se(c(\-|0|1)|47|mc|nd|ri)|sgh\-|shar|sie(\-|m)|sk\-0|sl(45|id)|sm(al|ar|b3|it|t5)|so(ft|ny)|sp(01|h\-|v\-|v )|sy(01|mb)|t2(18|50)|t6(00|10|18)|ta(gt|lk)|tcl\-|tdg\-|tel(i|m)|tim\-|t\-mo|to(pl|sh)|ts(70|m\-|m3|m5)|tx\-9|up(\.b|g1|si)|utst|v400|v750|veri|vi(rg|te)|vk(40|5[0-3]|\-v)|vm40|voda|vulc|vx(52|53|60|61|70|80|81|83|85|98)|w3c(\-| )|webc|whit|wi(g |nc|nw)|wmlb|wonu|x700|yas\-|your|zeto|zte\-/i',substr($user_agent,0,4))){

            return true;
        }else{
            return false;
        }
    }

    if( isset( $_SERVER['HTTP_ACCEPT'] ) ){
        if ( (strpos($_SERVER['HTTP_ACCEPT'], 'vnd.wap.wml') !== false)
            && (strpos($_SERVER['HTTP_ACCEPT'], 'text/html') === false || (strpos($_SERVER['HTTP_ACCEPT'], 'vnd.wap.wml') < strpos($_SERVER['HTTP_ACCEPT'], 'text/html')))
        )
        {
            return true;
        }
    }

    return false;
}

function compareFloat($a, $b, $esp = 0.000001)
{
    if (abs($a - $b) < $esp) {
        return true;
    }
    return false;
}

function getClientIp()
{
    if (@$_SERVER['HTTP_CLIENT_IP'] && $_SERVER['HTTP_CLIENT_IP'] != 'unknown') {
        $ip = $_SERVER['HTTP_CLIENT_IP'];
    } elseif (@$_SERVER['HTTP_X_FORWARDED_FOR'] && $_SERVER['HTTP_X_FORWARDED_FOR'] != 'unknown') {
        $ip = $_SERVER['HTTP_X_FORWARDED_FOR'];
    } else {
        $ip = $_SERVER['REMOTE_ADDR'];
    }
    return preg_match('/^\d[\d.]+\d$/', $ip) ? $ip : '';
}

function Now()
{
    return date('Y-m-d H:i:s');
}

function getReferer()
{
    return empty($_SERVER['HTTP_REFERER']) ? '' : $_SERVER['HTTP_REFERER'];
}

function request_uri()
{

    $http_type = ($_SERVER['HTTPS']?'https':$_SERVER['REQUEST_SCHEME'])?:'http';
    $host = $_SERVER['SERVER_NAME']?:$_SERVER['HTTP_HOST'];
    $port = $_SERVER['SERVER_PORT'];
    //$script = $_SERVER['PHP_SELF'];  // /ss/index.php
    //$query = $_SERVER['QUERY_STRING'];
    $uri = $_SERVER['REQUEST_URI'];
    if( $port == 80 ){
        return $http_type.'://'. $host . $uri;
    }else{
        return $http_type.'://'. $host . ':'. $port . $uri;
    }


}


