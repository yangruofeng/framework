<?php
/**
 * Created by PhpStorm.
 * User: sahara
 * Date: 2017/11/16
 * Time: 17:13
 */
class fileControl extends apiControl
{

    public function uploadFileOp()
    {
        $params = array_merge($_GET,$_POST);
        $upload = new uploadFile();
        //return $_FILES;
        $rt = $upload->upload('file');
        return $rt;
    }
}