<?php
/**
 * Created by PhpStorm.
 * User: sahara
 * Date: 2017/11/15
 * Time: 17:09
 */

class countryModel extends tableModelBase
{

    /*public $schema = array(

        array("Field" => "uid", "Type" => "int(11) unsigned", "Null" => "", "Key" => "PRI", "Default" => "", "Extra" => "auto_increment"),
        array("Field" => "code", "Type" => "varchar(5)", "Null" => "", "Key" => "", "Default" => "", "Extra" => ""),
        array("Field" => "name", "Type" => "varchar(50)", "Null" => "", "Key" => "", "Default" => "", "Extra" => ""),
        array("Field" => "population", "Type" => "int(11)", "Null" => "", "Key" => "", "Default" => "", "Extra" => ""),

    );*/


    function __construct()
    {
        parent::__construct('country');
    }
}
