<?php
/**
 * Created by PhpStorm.
 * User: sahara
 * Date: 2017/11/15
 * Time: 11:37
 */

$config['db_conf'] = array(

    'db_default' => array(
        "db_type"=>"mysql",
        "db_host"=>"127.0.0.1",
        "db_user"=>"root",
        "db_pwd"=>"root",
        "db_name"=>"test",
        "db_port"=>3306
    )

);

$config['global_resource_url'] = 'http://localhost/_sl/framework/resource';

$config['api_entry_url'] = 'http://localhost/_sl/framework/app/api/v1';