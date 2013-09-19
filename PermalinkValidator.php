<?php

/**
* @package permalink
*/
class PermalinkValidator extends CRegularExpressionValidator
{
    // public $pattern = '/^[_a-z0-9\/\-]+$/i';

    public function __construct()
    {
        $this->pattern = '/^['.Permalink::$characters.']+$/i';
        $this->message = Yii::t('permalink', '{attribute} contains forbidden symbols. Only latin letters, digits, _, - and backslash are allowed.');
    }
}