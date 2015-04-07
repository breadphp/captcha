<?php
/**
 * Bread PHP Framework (http://github.com/saiv/Bread)
 * Copyright 2010-2012, SAIV Development Team <development@saiv.it>
 *
 * Licensed under a Creative Commons Attribution 3.0 Unported License.
 * Redistributions of files must retain the above copyright notice.
 *
 * @copyright  Copyright 2010-2012, SAIV Development Team <development@saiv.it>
 * @link       http://github.com/saiv/Bread Bread PHP Framework
 * @package    Bread
 * @since      Bread PHP Framework
 * @license    http://creativecommons.org/licenses/by/3.0/
 */
namespace Bread\CAPTCHA;

use Bread\Configuration\Manager as Configuration;
use Exception;

class Manager
{

    protected static $drivers = array();
    protected static $mapping = array();

    public static function register($vendor, $class, $domain = '__default__')
    {
        if (is_string($vendor)) {
            if (!isset(static::$drivers["{$vendor}@{$domain}"])) {
                static::$drivers["{$vendor}@{$domain}"] = static::factory($vendor, $domain);
            }
            static::$mapping["{$class}@{$domain}"] = static::$drivers["{$vendor}@{$domain}"];
        } else {
            static::$mapping["{$class}@{$domain}"] = $vendor;
        }
        return static::$mapping["{$class}@{$domain}"];
    }

    public static function driver($class, $domain = '__default__')
    {
        $classes = class_parents($class);
        array_unshift($classes, $class);
        foreach ($classes as $c) {
            if (isset(static::$mapping["{$c}@{$domain}"])) {
                return static::$mapping["{$c}@{$domain}"];
            } elseif ($vendor = Configuration::get($c, 'vendor', $domain)) {
                return static::register($vendor, $c, $domain);
            }
        }
        throw new Exceptions\DriverNotRegistered($class);
    }

    public static function factory($vendor, $domain = '__default__')
    {
        if (!$Driver = Configuration::get(__CLASS__, "drivers.$vendor")) {
            throw new Exception("Driver for {$scheme} not found.");
        }
        if (!is_subclass_of($Driver, 'Bread\CAPTCHA\Interfaces\Driver')) {
            throw new Exception("{$Driver} isn't a valid driver.");
        }
        return new $Driver($domain);
    }
}

Configuration::defaults('Bread\CAPTCHA\Manager', array(
    'drivers' => array(
        'recaptcha' => 'Bread\CAPTCHA\Drivers\GoogleReCAPTCHA',
        'recaptchav1' => 'Bread\CAPTCHA\Drivers\GoogleReCAPTCHAv1',
        'saiv' => 'Bread\CAPTCHA\Drivers\SaivCAPTCHA'
    )
));
