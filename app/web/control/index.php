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
        Tpl::output('title','Test');
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

    public function api_upload_imgOp()
    {

        //$url = 'http://localhost/microbank/microbank/api/v1/member.cert.id.php';
        $url = 'http://bank.mekong24.com/microbank/api/v1/member.cert.id.php';
        $img = APP_RESOURCE_PATH.'/images/load10.gif';
        $hand = APP_RESOURCE_PATH.'/images/hand.jpg';
        $front = APP_RESOURCE_PATH.'/images/front.jpg';
        $back = APP_RESOURCE_PATH.'/images/back.jpg';
        $data = array(
            'member_id' => 7,
            'token' => '3b541b92a7f725a5ca45c956cd7277bb',
            'hand_photo' => new CURLFile($hand),
            'front_photo' => new CURLFile($front),
            'back_photo' => new CURLFile($back),
        );
        $rt = http::http_request('http','post',$url,$data);

        print_r($rt);die;
    }

    public function family_book_certOp()
    {
        //$url = 'http://localhost/microbank/microbank/api/v1/member.cert.familybook.php';
        $url = 'http://bank.mekong24.com/microbank/api/v1/member.cert.familybook.php';
        $hand = APP_RESOURCE_PATH.'/images/f_hand.jpg';
        $front = APP_RESOURCE_PATH.'/images/f_back.jpg';
        $back = APP_RESOURCE_PATH.'/images/f_front.jpg';
        $household = APP_RESOURCE_PATH.'/images/household.jpg';
        $data = array(
            'member_id' => 7,
            'token' => '3b541b92a7f725a5ca45c956cd7277bb',  // 894d41b095e77b64a0dc1dba92841672  3b541b92a7f725a5ca45c956cd7277bb
            'hand_photo' => new CURLFile($hand),
            'front_photo' => new CURLFile($front),
            'back_photo' => new CURLFile($back),
            'householder_photo' => new CURLFile($household),
        );
        $rt = http::http_request('http','post',$url,$data);

        print_r($rt);die;
    }

    public function workCertOp()
    {
        $url = 'http://localhost/microbank/microbank/api/v1/member.cert.work.php';
        //$url = 'http://bank.mekong24.com/microbank/api/v1/member.cert.familybook.php';
        $hand = APP_RESOURCE_PATH.'/images/f_hand.jpg';
        $front = APP_RESOURCE_PATH.'/images/f_back.jpg';
        $back = APP_RESOURCE_PATH.'/images/f_front.jpg';
        $household = APP_RESOURCE_PATH.'/images/household.jpg';
        $data = array(
            'member_id' => 12,
            'company_name' => 'xx',
            'company_address' => '四川省成都市高新区',
            'position' => '总经理',
            'is_government' => 1,
            'token' => '701b0c448f5d36546e13958793d97208',  // 894d41b095e77b64a0dc1dba92841672  3b541b92a7f725a5ca45c956cd7277bb
            'photo1' => new CURLFile($hand),
            'photo2' => new CURLFile($front),
        );
        $rt = http::http_request('http','post',$url,$data);

        print_r($rt);die;
    }

    public function familyRelationOp()
    {
        $url = 'http://localhost/microbank/microbank/api/v1/member.cert.family.relationship.php';
        //$url = 'http://bank.mekong24.com/microbank/api/v1/member.cert.familybook.php';

        $front = APP_RESOURCE_PATH.'/images/front.jpg';
        $data = array(
            'member_id' => 12,
            'relation_type' => '父子',
            'relation_name' => '韦小宝',
            'relation_cert_type' => '身份证',
            'relation_cert_photo' => new CURLFile($front),
            'country_code' => '86',
            'relation_phone' => '18902461905',
            'token' => '701b0c448f5d36546e13958793d97208',  // 894d41b095e77b64a0dc1dba92841672  3b541b92a7f725a5ca45c956cd7277bb

        );
        $rt = http::http_request('http','post',$url,$data);

        print_r($rt);die;
    }

}