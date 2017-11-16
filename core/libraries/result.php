<?php
/**
 * Created by PhpStorm.
 * User: sahara
 * Date: 2017/11/15
 * Time: 14:45
 */
class result
{
    public $STS = true;
    public $MSG = '';
    public $DATA = null;
    public $ERROR_NO = 0;

    public function __construct($sts=true,$msg='',$data=null,$err_no=0)
    {
        $this->STS = $sts;
        $this->MSG = $msg;
        $this->DATA = $data;
        $this->ERROR_NO = $err_no;
    }

    public function toArray()
    {
        return array(
            'STS' => $this->STS,
            'MSG' => $this->MSG,
            'DATA' => $this->DATA,
            'ERR_NO' => $this->ERROR_NO
        );
    }
}