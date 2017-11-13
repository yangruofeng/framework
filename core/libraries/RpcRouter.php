<?php
class RpcRouter
{
	static $_preSetData=array();
	static $_last_json_obj=array();//FOR DEBUG
	public static function _gzip_output($buffer){
		$len = strlen($buffer);
		if(substr_count($_SERVER['HTTP_ACCEPT_ENCODING'], 'gzip')){
			$gzbuffer = gzencode($buffer);
			$gzlen = strlen($gzbuffer);
			if ($len > $gzlen) {
				header("Content-Length: $gzlen");
				header("Content-Encoding: gzip");
				print $gzbuffer;
				return;
			}
		}
		header("Content-Length: $len");
		print $buffer;
		return;
	}

	public static function _log_($log_type,$content){
		if(is_object($content) || is_array($content)){
			$content=json_encode($content);
		}	 
		$rt="";
		if(!defined('_LOG_')){ throw new Exception("//_LOG_ not defined to call logger"); }
		$suffix="\n";//for all
		$rt=_LOG_ .'/'.$log_type."-".date('Ymd').".log";
		file_put_contents($rt, $content.$suffix, FILE_APPEND);
		return $rt;
	}

    public static function parse_conf(&$setting_config){
        $it_config=$GLOBALS['config'];
        $setting_config = $it_config;
    }

    public static function init(){
        global $setting_config;
        self::parse_conf($setting_config);
    }

    public static function handle($_p = array()){
        self::startSession();
        $param=array_merge(array(), $_GET, $_POST); //按道理只要request就能取到所有参数了。好像说可能存在配置问题。
        $php_input = file_get_contents('php://input');
        if($php_input && !$GLOBALS['HTTP_RAW_POST_DATA']) $GLOBALS['HTTP_RAW_POST_DATA']=$php_input;//store for later usage if needed
        if($php_input){
            $php_input=json_decode($php_input,true);

            if(is_array($php_input)){
                $param=array_merge($param,$php_input);
            }
        }

        $ctrl=(($param['_c']?:$param['_cls'])?:$param['class'])?:$param['act'];
        $ctrl=$ctrl?:$param['_api'];
        $opt=($param['_m']?:$param['_a']?:$param['method'])?:$param['op'];
        $opt=$opt?:$param['_opt'];
        $data=($param['_p']?:$param['data'])?:$param['param'];
        $_POST=array_merge(array(),$_POST?:array(),$data?:array());

        if($ctrl) {
            $class = ($ctrl?:'index') . "Control"; //特殊规则
            $method = ($opt ?: "index") . "Op";
        }else{
            $class=$_p['defaultClass'];
            $method=$_p['defaultMethod'];
        }

        try{
            @include_once(APP_PATH . "/control/" . $ctrl . ".php");
            ob_start();
            $obj=new $class;
            $rt=$obj->$method($data);
        }catch (Exception $ex){
            $rt=(global_error_handler($ex->getFile(),$ex->getLine(),$ex->getMessage(),$ex->getTrace(),$ex->getCode()));
            $rt=new result(false,$ex->getMessage(),$rt);
            debug("IT-CHECK",$rt);
        }

        if(is_array($rt) || is_object($rt)){
            ob_get_clean();
            //$rt['debug_trace']=Tpl::showTrace();
            if(is_object($rt)){
                $rt=obj2array($rt,true);
            }

            if (!$rt['STS']) {
                $now = @date('Y-m-d H:i:s',time());
                $url = $_SERVER['REQUEST_URI'] ? $_SERVER['REQUEST_URI'] : $_SERVER['PHP_SELF'];
                $url .= " ( act={$_GET['act']}&op={$_GET['op']} ) ";
                $message = json_encode($rt);
                logger::record("error", "[{$now}] {$url}\r\n{$message}\r\n");
            }

            if(C("debug")){
                $rt['logger']=logger::History();
            } else {
                unset($rt['MSG']);
            }

            $output = json_encode($rt);
        }else{
            $output = ob_get_clean();
        }
//        self::_gzip_output($output);//try gzip output
        print $output;
        ob_end_flush();
    }


    public static function startSession(){

        $session_config = C('session');
        $session_save_handler = $session_config['save_handler'];
        $session_save_path = $session_config['save_path'];
        if(!empty($session_save_handler) && !empty($session_save_path)){
            @ini_set("session.save_handler", $session_save_handler);
            @ini_set("session.save_path", $session_save_path);
        }else if (ini_get("session.save_handler") == "files") {
            //默认以文件形式存储session信息
            session_save_path(_DATA_PATH_ . '/session');
        }

        $_s=$_GET['token_key']?:($_POST['token_key']?:$_COOKIE['PHPSESSID']);
        if(!$_s){
            Str::quickRandom(8);
        }
        session_id($_s);
        session_start();
        $_SESSION['_s']=$_s;
        session_write_close();
    }
}

