<?php
/**
 * Created by Roquie.
 * E-mail: roquie0@gmail.com
 * GitHub: Roquie
 * Date: 18/01/2017
 */

/**
 * @param $size
 * @return string
 */
function format_bytes($size)
{
    $unit = ['b', 'kb', 'mb', 'gb', 'tb', 'pb'];
    return @round($size / pow(1024, ($i = floor(log($size, 1024)))), 2) . ' ' . $unit[$i];
}

/**
 * Все локальные IP-адреса исключаются.
 *
 * @param $string
 * @return bool
 */
function valid_ip($string)
{
    // Fails validation for the following loopback IPv4 range: 127.0.0.0/8
    // This flag does not apply to IPv6 addresses
    $options = ['options' => function($value) {
        return filter_var($value, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6) ? $value :
            (((ip2long($value) & 0xff000000) == 0x7f000000) ? false : $value);
    }];

    return filter_var($string, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE) && filter_var($string, FILTER_CALLBACK, $options);
}
