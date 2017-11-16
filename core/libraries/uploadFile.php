<?php
/**
 * Created by PhpStorm.
 * User: sahara
 * Date: 2017/11/15
 * Time: 17:30
 */

/**
 * 文件上传类
 * Class uploadFile
 */
class uploadFile
{
    public $root_save_path = '';  // 外部可任意定义位置,最终路径是 root_save_path/default_dir/relative_path/realFilename
    public $default_dir='default';

    public $base_name ;
    public $file_name='';
    public $file_ext;
    public $file_size;
    public $dir_type=1;
    public $error='';
    public $relative_path;
    public $full_path='';


    private $upload_files;

    private $max_size = 524288000; // 500M
    private $allow_type = null;

    private $return = array(
        'STS' => true,
        'MSG' => 'success',
        'ERROR_NO' => 0
    );

    function __construct()
    {
    }

    private function result($sts=false,$msg='',$data=null,$err_no=0)
    {
        return array(
            'STS' => $sts,
            'MSG' => $msg,
            'DATA' => $data,
            'ERROR_NO' => $err_no
        );
    }

    public function set($name,$value)
    {
        $this->$name = $value;
    }

    public function get($name)
    {
        return $this->$name;
    }

    public function upload($input)
    {
        $temp_file = $this->getTempFilePath();
        if( !$temp_file['STS'] ){
            return $this->result(false,$temp_file['MSG']);
        }
        $temp_file = $temp_file['DATA'];

        // html5
        if( isset($_SERVER['HTTP_CONTENT_DISPOSITION']) &&
            preg_match('/attachment;\s+name="(.+?)";\s+filename="(.+?)"/i',$_SERVER['HTTP_CONTENT_DISPOSITION'],$info)
        ){

            file_put_contents($temp_file, file_get_contents("php://input"));
            $localName = urldecode($info[2]);

        }else{
            $file = @$_FILES[$input];
            $name = $file['name'];
            $type = $file['type'];
            $size = $file['size'];
            $temp_name = $file['tmp_name'];
            $error = $file['error'];
            if( $error > 0 ){
                switch( $error ){
                    case UPLOAD_ERR_INI_SIZE:  // 1
                        $err = 'Over php.ini upload_max_filesize.';
                        break;
                    case UPLOAD_ERR_FORM_SIZE:  // 2
                        $err = 'Over html form MAX_FILE_SIZE.';
                        break;
                    case UPLOAD_ERR_PARTIAL: // 3
                        $err = 'The file was only partially uploaded.';
                        break;
                    case UPLOAD_ERR_NO_FILE:
                        $err = 'No file was uploaded.';
                        break;
                    case UPLOAD_ERR_NO_TMP_DIR:
                        $err = 'The servers temporary folder is missing.';
                        break;
                    case UPLOAD_ERR_CANT_WRITE:
                        $err = 'Failed to write to the temporary folder.';
                        break;
                    case UPLOAD_ERR_EXTENSION:
                        $err = 'Upload Interrupt';
                        break;
                    default:
                        $err = 'Unknown err';
                }
                return $this->result(false,$err,null,$error);
            }



            if( !@move_uploaded_file($temp_name,$temp_file) ){
                return $this->result(false,'Move upload file fail.');
            }
            $localName = $name;

        }


        $fileInfo = pathinfo($localName);
        $this->set('base_name',$fileInfo['basename']);
        $ext = $fileInfo['extension'];
        if( !empty($this->allow_type) && !in_array(strtolower($ext),$this->allow_type) ){
            @unlink($temp_file);
            return $this->result(false,'File type not allowed.',null,101);
        }
        $size = filesize($temp_file);
        if( $size > $this->max_size ){
            @unlink($temp_file);
            return $this->result(false,'Over setting max size.',null,102);
        }

        if ($this->file_name) {
            $realFileName = $this->file_name;
        } else {
            $realFileName = $this->getRealFilename($ext);
        }

        $relative_path = $this->getRelativePath();

        if( $this->root_save_path ){
            $full_path = $this->root_save_path.'/'.$relative_path;
        }else{
            $full_path = _UPLOAD_PATH_.'/'.$relative_path;
        }

        if( !is_dir($full_path) ){
            $mk = mkdir($full_path,0755,true);
            if( !$mk ){
                return $this->result(false,'Create file dir fail',null,103);
            }
        }

        $file_relative_path = $relative_path.'/'.$realFileName;
        $file_full_path = $full_path.'/'.$realFileName;
        if( !rename($temp_file,$file_full_path)){
            return $this->result(false,'Rename file fail.',null,104);
        }
        @chmod($file_full_path,0755);
        $this->file_name = $realFileName;
        $this->file_size = $size;
        $this->file_ext = $ext;
        $this->relative_path = $file_relative_path;
        $this->full_path = $file_full_path;
        @unlink($temp_file);
        return $this->result(true,'success',array(
            'file_name' => $realFileName,
            'file_size' => $size,
            'file_ext' => $ext,
            'relative_path' => $file_relative_path,
            'full_path' => $file_full_path
        ));


    }

    private function getRealFilename($ext,$prefix='',$endfix='')
    {
        $name = sprintf('%04d',mt_rand(1000,9999)).sprintf('%010d',time()).sprintf('%04d',microtime()*10000);
        $name = $prefix.$name.$endfix.'.'.$ext;
        return $name;
    }

    private function getRelativePath()
    {
        $s_path = $this->getSystemDirPath();
        $s_path = trim($s_path,'/');
        if( $this->default_dir ){
            $s_path = $this->default_dir.'/'.$s_path;
        }
        return $s_path;
    }

    public function getSystemDirPath()
    {
        switch( $this->dir_type ){
            case 1:  // 年月日的目录
                $temp = date('Y-m-d'); // 2017-11-16/a.png
                break;
            case 2:
                $temp = date('Y'); // 2017/a.png
                break;
            case 3:
                $temp = date('Y').'/'.date('m').'/'.date('d'); // 2017/11/16/a.png
                break;
            case 4:
                $temp = md5(time());  // jkgjhendfg215eaef14d5fe2aefdg3es/a.png
                break;
            case 5:
                $temp = '';  // a.png
                break;
            default:
                $temp = date('Ymd'); // 20171116/a.png
        }
        return $temp;
    }

    private function getTempFilePath()
    {

        $temp = _UPLOAD_PATH_.'/tmp';
        if( !is_dir($temp) ){
            if( !@mkdir($temp,0755,true) ){
                return $this->result(false,'make tmp dir fail.');
            }
        }
        $name = sprintf('%010d',time()).sprintf('%04d',mt_rand(0,9999)).sprintf('%04d',microtime()*10000);
        $name .= '.tmp';
        $temp = $temp.'/'.$name;
        return $this->result(true,'',$temp);
    }


}