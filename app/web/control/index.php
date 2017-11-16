<?php
/**
 * Created by PhpStorm.
 * User: sahara
 * Date: 2017/11/13
 * Time: 18:31
 */
class indexControl extends control
{


    public function indexOp()
    {

        //$m = new countryModel();
        //print_r($m->find(1));
        Tpl::output('title','Hello,word!');
        Tpl::setLayout('default_layout');
        Tpl::show('index');

    }

    public function upload_fileOp()
    {
        $img = APP_RESOURCE_PATH.'/files/smarithiesak-member.apk';
        $data = array(
            'file' => new CURLFile($img)
        );
        $rt = http::http_request('http','post',API_ENTRY_URL.'/upload.file.php',$data);
        $rt = my_json_decode($rt);

        print_r($rt['data']);
    }

}