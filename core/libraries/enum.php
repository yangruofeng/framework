<?php
/**
 * Created by PhpStorm.
 * User: sahara
 * Date: 2017/11/16
 * Time: 13:37
 */
abstract class enum
{
    function __construct()
    {
    }

    public function getConstants()
    {
        $class_name = get_class($this);
        $ref = new ReflectionClass($class_name);
        $constants = $ref->getConstants();
        return $constants;
    }

    public function getConstantsKeys()
    {
        $arr = $this->getConstants();
        return array_keys($arr);
    }

    public function getConstantsValues()
    {
        $arr = $this->getConstants();
        return array_values($arr);
    }

    public function getAllProperties()
    {
        $class_name = get_class($this);
        $ref = new ReflectionClass($class_name);
        $prop = $ref->getProperties();
        $list = array();
        foreach( $prop as $v ){
            $name = $v->getName();
            $list[] = $name;
        }
        return $list;
    }

    public function getPublicPropertyValues()
    {
        $class_name = get_class($this);
        $ref = new ReflectionClass($class_name);
        $prop = $ref->getProperties(ReflectionProperty::IS_PUBLIC);
        $list = array();
        foreach( $prop as $v ){
            $name = $v->getName();
            $list[$name] = $v->getValue($this);
        }
        return $list;
    }


}

