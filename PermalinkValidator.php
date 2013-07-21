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
        $this->message = Yii::t('app', '{attribute} содержит запрещенные символы. Разрешены только латинские буквы, цифры, нижнее подчеркивание, тире и обратный слэш.');
    }
}