<?php
/**
 * Created by PhpStorm.
 * User: sahara
 * Date: 2017/11/13
 * Time: 18:08
 */

$config = array(

    'session' => array(
        'save_handler' => 'files',
        'save_path' => APP_PATH."/data/session"
    ),

    'db_conf' => array(

        'db_default' => array(
            "db_type"=>"mysql",
            "db_host"=>"127.0.0.1",
            "db_user"=>"root",
            "db_pwd"=>"",
            "db_name"=>"",
            "db_port"=>3306
        )
    )


);

$config['debug']=true;
