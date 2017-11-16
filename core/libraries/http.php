<?php
/**
 * Created by PhpStorm.
 * User: sahara
 * Date: 2017/11/16
 * Time: 14:52
 */
class http
{
    function __construct()
    {
    }

    private $client;
    public $http_type = 'http';
    private static $_instance = null;

    public function head($uri,array $body){
        return self::request($this->client, 'HEAD', $uri, $body);
    }

    public function get($uri, array $query = array()) {
        if (!empty($query)) {
            $uri = $uri . '?' . http_build_query($query);
        }
        return self::request($this->client, 'GET', $uri);
    }
    public function post($uri, array $body = array()) {
        return self::request($this->client, 'POST', $uri, $body);
    }

    public function put($uri, array $body = array()) {
        return self::request($this->client, 'PUT', $uri, $body);
    }

    public function delete($uri, array $body = array()) {
        return self::request($this->client, 'DELETE', $uri, $body);
    }

    public function upload($uri, array $body = array()) {
        $headers = [
            'Content-Type: multipart/form-data',
            'Connection: Keep-Alive'
        ];
        return self::request($this->client, 'UPLOAD', $uri, $body, $headers);
    }

    public static function getInstance($client) {
        if (is_null(self::$_instance) || !(self::$_instance instanceof self)) {
            self::$_instance = new self($client);
        }
        return self::$_instance;
    }


    private function __clone() {}

    private  function request($client, $method, $uri, array $body = array(), array $headers = array()) {
        $default_headers = [
            'Content-Type: application/json',
            'Connection: Keep-Alive'
        ];

        $method = strtoupper($method);
        $ch = curl_init();
        $options = array(
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HEADER => true,
            CURLOPT_HTTPHEADER => (empty($headers) ? $default_headers : $headers),
            CURLOPT_USERAGENT => '',
            CURLOPT_CONNECTTIMEOUT => 30,
            CURLOPT_TIMEOUT => 120,

            CURLOPT_HTTPAUTH => CURLAUTH_BASIC,
            CURLOPT_USERPWD => 'username:password',

            CURLOPT_URL => $uri,
            CURLOPT_CUSTOMREQUEST => ('UPLOAD' == $method) ? 'POST' : $method
        );
        if ( $this->http_type == 'https') {
            $options[CURLOPT_SSL_VERIFYPEER] = false;
            $options[CURLOPT_SSL_VERIFYHOST] = 0;
        }

        if (!empty($body)) {
            if ('UPLOAD' == $method) {
                if (class_exists('\CURLFile')) {
                    $options[CURLOPT_SAFE_UPLOAD] = true;
                    $options[CURLOPT_POSTFIELDS] = ['filename' => new \CURLFile($body['path'])];
                } else {
                    # TODO
                    if (defined('CURLOPT_SAFE_UPLOAD')) {
                        $options[CURLOPT_SAFE_UPLOAD] = false;
                        $options[CURLOPT_POSTFIELDS] = '';
                    }
                }
            } else {
                $options[CURLOPT_POSTFIELDS] = $body;  // json_encode($body)
            }
        }

        if( $method == 'HEAD'){
            $options[CURLOPT_NOBODY] = true;
        }

        curl_setopt_array($ch, $options);
        $output = curl_exec($ch);

        if($output === false) {
            return array(
                'ret' => 0,
                'msg' => "Error Code:" . curl_errno($ch) . ", Error Message:".curl_error($ch),
                'data' => null
            );
        } else {
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $header_size = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
            $header_text = substr($output, 0, $header_size);
            $body = substr($output, $header_size);
            $headers = array();
            foreach (explode("\r\n", $header_text) as $i => $line) {
                if (!empty($line)) {
                    if ($i === 0) {
                        $headers[0] = $line;
                    } else if (strpos($line, ": ")) {
                        list ($key, $value) = explode(': ', $line);
                        $headers[$key] = $value;
                    }
                }
            }
            $response['headers'] = $headers;
            $response['body'] = json_decode($body, true);
            $response['http_code'] = $httpCode;
        }
        curl_close($ch);
        return array(
            'ret' => 1,
            'msg' => 'success',
            'data' => $response
        );

    }


    /**
     * 向Rest服务器发送请求
     * @param string $http_type http类型,比如https
     * @param string $method 请求方式，比如POST
     * @param string $url 请求的url
     * @return string $data 请求的数据
     */
    public static function http_request($http_type, $method, $url, $data=array())
    {
        $default_headers = array(
            'Content-Type: application/json',
            'Connection: Keep-Alive'
        );
        $ch = curl_init();

        //curl_setopt($ch,CURLOPT_HEADER,true);  // 有文件上传不能用 application/json
        //curl_setopt($ch,CURLOPT_HTTPHEADER,$default_headers);

        if (strstr($http_type, 'https'))
        {
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
        }

        if ($method == 'post')
        {
            curl_setopt($ch, CURLOPT_POST, 1);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
        } else
        {
            if( $data ){

                if( is_array($data) ){
                    $query = http_build_query($data);
                }else{
                    $query = (string) $data;
                }
                $url = $url . '?' . $query;
            }

        }

        curl_setopt($ch, CURLOPT_SAFE_UPLOAD, false);
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_HEADER, 0);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_TIMEOUT,30);//超时时间s

        try
        {
            $ret=curl_exec($ch);
            $info = curl_getinfo($ch);

        }catch(Exception $e)
        {
            curl_close($ch);
            return json_encode(array('ret'=>0,'msg'=>'failure','data'=>null));
        }
        curl_close($ch);
        return json_encode(array('ret'=>1,'msg'=>'success','data'=>$ret,'info'=>$info));
    }


    /**
     * HTTP REQUEST 封装
     * @param string $http_type,连接方式，http https
     * @param string $method HTTP REQUEST方法，包括PUT、POST、GET、OPTIONS、DELETE
     * @param string $uri 请求路径，包括get参数
     * @param array $headers 请求需要的特殊HTTP HEADERS
     * @param array $body 需要POST发送的数据
     *
     * @return mixed
     */
    public function _do_request($http_type,$method, $uri,$body= array(), $headers = array(),  $file_handle= NULL) {


        $ch = curl_init($uri);

        if( $http_type == 'https'){
            curl_setopt($ch,CURLOPT_SSL_VERIFYPEER,false);
            curl_setopt($ch,CURLOPT_SSL_VERIFYHOST,0);
        }
        $_headers = array('Expect:');
        if (!is_null($headers) && is_array($headers)){
            foreach($headers as $k => $v) {
                array_push($_headers, "{$k}: {$v}");
            }
        }

        $length = 0;
        $date = gmdate('D, d M Y H:i:s \G\M\T');

        if (!is_null($body)) {
            if(is_resource($body)){
                fseek($body, 0, SEEK_END);
                $length = ftell($body);
                fseek($body, 0);

                array_push($_headers, "Content-Length: {$length}");
                curl_setopt($ch, CURLOPT_INFILE, $body);
                curl_setopt($ch, CURLOPT_INFILESIZE, $length);
            } else {
                curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
            }
        } else {
            array_push($_headers, "Content-Length: {$length}");
        }

        //array_push($_headers, "Authorization: ");
        array_push($_headers, "Date: {$date}");

        curl_setopt($ch, CURLOPT_HTTPHEADER, $_headers);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_HEADER, 1);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        //curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 0);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);

        if ($method == 'PUT' || $method == 'POST') {
            curl_setopt($ch, CURLOPT_POST, 1);
        } else {
            curl_setopt($ch, CURLOPT_POST, 0);
        }

        if ($method == 'GET' && is_resource($file_handle)) {
            curl_setopt($ch, CURLOPT_HEADER, 0);
            curl_setopt($ch, CURLOPT_FILE, $file_handle);
        }

        if ($method == 'HEAD') {
            curl_setopt($ch, CURLOPT_NOBODY, true);
        }

        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);

        if ($http_code == 0){

            return array(
                'ret' => 0,
                'msg' => 'Connection Failed',
            );
        }

        curl_close($ch);

        return array(
            'ret' => 1,
            'msg' => 'success',
            'data' => $response
        );

       /* $header_string = '';
        $body = '';

        if ($method == 'GET' && is_resource($file_handle)) {
            $header_string = '';
            $body = $response;
        } else {
            list($header_string, $body) = explode("\r\n\r\n", $response, 2);
        }*/



    }

}