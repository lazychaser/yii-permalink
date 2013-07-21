<?php

/**
* @package Permalink
*/
class Permalink
{
    static $characters = '_a-z0-9\/\-';

    static public function isValid($permalink)
    {
        return preg_match('/['.self::$characters.']/i', $permalink);
    }

    static public function make($title)
    {
        return preg_replace(
            array('/\s+/', '/[^'.self::$characters.']/i'), 
            array('_', ''), 
            trim(strtolower($title), ' /')
        );
    }
}