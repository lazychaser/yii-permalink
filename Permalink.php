<?php

/**
* @package Permalink
*/
class Permalink
{
    /**
     * Valid characters.
     * Used in RegExp.
     *
     * @var string
     */
    static $characters = '_a-z0-9\/\-';

    /**
     * Checks if string is valid permalink.
     *
     * @param  string  $permalink the string to be checked.
     *
     * @return boolean            
     */
    static public function isValid($permalink)
    {
        return preg_match('/['.self::$characters.']/i', $permalink);
    }

    /**
     * Converts any title to a valid permalink.
     * Removes invalid characters, translates spaces to "_" and 
     * converts to lower case.
     *
     * @param  string $title the string to be converted.
     *
     * @return [type]        [description]
     */
    static public function make($title)
    {
        return preg_replace(
            array('/\s+/', '/[^'.self::$characters.']/i'), 
            array('_', ''), 
            trim(strtolower($title), ' /')
        );
    }
}