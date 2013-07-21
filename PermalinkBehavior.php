<?php

/**
 * This behavior allows to automatically attach permalink to a model.
 * 
 * Target model class must have method getRawPermalink which returns raw pemalink.
 * It will be passed thru Permalink::make to remove unwanted chars.
 * 
* @package permalink
*/
class PermalinkBehavior extends CBehavior
{
    static private $_manager;

    /**
     * Whether to validate permalink before saving.
     * Error message is set for attribute _permalink by default.
     * @var boolean
     */
    public $validate = true;

    /**
     * The attribute which will recieve an error message.
     * The default value is "_permalink"
     * @var string
     */
    public $validateAttribute;

    public function events()
    {
        $events = array(
            'onAfterSave' => 'afterSave',
        );

        if ($this->validate) {
            $events['onBeforeValidate'] = 'beforeValidate';
        }

        return $events;
    }

    /**   
     * @return boolean whether owner has permalink
     */
    public function hasPermalink()
    {
        return $this->getManager()->hasPermalink($this->getOwner());
    }

    /**
     * Updates permalink.
     * @return bool whether permalink has changed (or removed)
     */
    public function updatePermalink()
    {
        $owner = $this->getOwner();
        $manager = $this->getManager();
        $permalink = $this->getNewPermalink();

        if (!$permalink) {
            if ($this->hasPermalink()) {
                $manager->removePermalinks($owner);
                return true;
            }

            return false;
        }

        return $manager->setPermalink($owner, $permalink);
    }

    ////////////////
    // Properties //
    ////////////////

    public function getPermalink()
    {
        return $this->getManager()->getPermalink($this->getOwner());
    }

    public function getNewPermalink()
    {
        return Permalink::make($this->getOwner()->getRawPermalink());
    }

    // public function setPermalink($value)
    // {
    //     $this->getManager()->setPermalink($this->getOwner(), $value);
    // }

    /**
     * @return PermalinkManager
     */
    public function getManager()
    {
        if (self::$_manager == null) {
            if (!self::$_manager = Yii::app()->getComponent('permalinkManager'))
                throw new CException(Yii::t(
                    'permalink', 
                    'Could not find component "permalinkManager".'
                ));
        }

        return self::$_manager;
    }

    ////////////
    // Events //
    ////////////

    public function beforeValidate(CModelEvent $event)
    {
        $permalink = $this->getNewPermalink();

        if ($permalink != $this->getPermalink() && $this->getManager()->hasModel($permalink)) {
            $this->getOwner()->addError(
                $this->validateAttribute ? $this->validateAttribute : '_permalink', 
                Yii::t('permalink', 'Such permalink already exists.'
            ));
            $event->isValid = false;
        }
    }

    public function afterSave($event)
    {
        $this->updatePermalink();
    }
}