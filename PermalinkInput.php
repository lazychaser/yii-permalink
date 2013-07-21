<?php

/**
* @package permalink
*/
class PermalinkInput extends CInputWidget
{
    public $sourceInput;

    public $sync;

    public function run()
    {
        list($name, $id) = $this->resolveNameID();

        if ($hasModel = $this->hasModel()) {
            echo CHtml::activeTextField($this->model, $this->attribute, $this->htmlOptions);
        } else {
            echo CHtml::textField($name, $this->value, $this->htmlOptions);
        }

        if ($this->sourceInput === null) {
            return;
        }

        $cs = app()->clientScript;
        $cs->registerScriptFile(
            CHtml::asset(Yii::getPathOfAlias('ext').'/permalink/script.js'), 
            CClientScript::POS_END
        );

        $selector = is_array($this->sourceInput)
            ? CHtml::activeId($this->sourceInput[0], $this->sourceInput[1])
            : $this->sourceInput;

        $options = array(
            'selector' => '#'.$selector,
            'sync' => $this->sync,
        );

        $cs->registerScript($this->id, '$("#'.$id.'").permalinkInput('.CJavaScript::encode($options).');');
    }
}