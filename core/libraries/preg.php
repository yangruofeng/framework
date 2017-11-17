<?php
/**
 * Created by PhpStorm.
 * User: sahara
 * Date: 2017/11/17
 * Time: 13:59
 */
class preg
{
    public function __construct()
    {
    }

    /**
     * 国际通用格式验证，不能验证具体的国家电话
     * @param $mobile
     * @return int
     */
    public static function isMobile($mobile)
    {
        return preg_match('/^\+?[0-9]{5,25}$/',$mobile);  // 手机不考虑028-3345的格式
    }

    /**
     * 移动号码段:139、138、137、136、135、134、150、151、152、157、158、159、182、183、187、188、147
     * 联通号码段:130、131、132、136、185、186、145
     * 电信号码段:133、153、180、189
     * @param $phone
     */
    public static function isChinaPhone($phone)
    {
        $pattern = '/^( (13[0-9]) | (14[57]) | (15[0123789]) | (18[012356789]) )\d{8}$/';
        return preg_match($pattern,$phone);
    }

    /**
     * 是否合理的邮箱地址
     * @param $email
     * @return int
     */
    public static function isEmail($email)
    {
        return preg_match('/^[a-z0-9_-]+@[a-z0-9_-]+(\.[a-z0-9_-]+)+$/i',$email);
    }
}