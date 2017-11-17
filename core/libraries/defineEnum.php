<?php
/**
 * Created by PhpStorm.
 * User: sahara
 * Date: 2017/11/17
 * Time: 10:44
 */
class testEnum extends enum
{
    const ONE = 1;
    const TWO = 2;
    const THREE = 3;
    public $public = 'public';
    protected  $protected = 'protected';
    private $private = 'private';
    public static $static = 'static';
}