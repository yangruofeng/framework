<?php
/**
 * Created by PhpStorm.
 * User: sahara
 * Date: 2017/11/17
 * Time: 13:53
 */
class unicode
{
    public function __construct()
    {
    }

    // 单字符中文
    /**
     * utf8字符转换成Unicode字符
     * @param  [type] $utf8_str Utf-8 字符
     * @return [type]           Unicode 字符
     */
    public static function utf8_to_unicode($utf8_str) {
        $unicode = 0;
        $unicode = (ord($utf8_str[0]) & 0x1F) << 12;
        $unicode |= (ord($utf8_str[1]) & 0x3F) << 6;
        $unicode |= (ord($utf8_str[2]) & 0x3F);
        return dechex($unicode);
    }

    /**
     * Unicode字符转换成utf8字符
     * @param  [type] $unicode_str Unicode字符
     *  @return [type]              Utf-8字符
     */
    public static function unicode_to_utf8 ($unicode_str) {
        $utf8_str = '';
        $code = intval(hexdec($unicode_str));
        $ord_1 = decbin(0xe0 | ($code >> 12));
        $ord_2 = decbin(0x80 | (($code >> 6) & 0x3f));
        $ord_3 = decbin(0x80 | ($code & 0x3f));
        $utf8_str = chr(bindec($ord_1)).chr(bindec($ord_2)).chr(bindec($ord_3));
        return $utf8_str;
    }
}