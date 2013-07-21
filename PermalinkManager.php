<?php

/**
* @package permalink
*/
class PermalinkException extends CException {}

/**
* @package permalink
*/
class PermalinkExistsException extends PermalinkException {}

/**
* @package permalink
*/
class PermalinkManager extends CComponent
{
    public $action = 'view';
    public $connectionId = 'db';

    public $versioning = true;

    // alias => model/model_id
    private $_modelMap;

    // model/model_id => alias
    private $_permalinkMap;
    
    private $_db;

    public function init()
    {
        $db = $this->getDbConnection();

        // We need items to be sorted by version.
        // But we don't need that explicitly because when
        // version is changed primary key is also changed,
        // so new version are always at the end
        $items = $db->createCommand("
            SELECT model, model_id, permalink, version
            FROM {{permalinks}}"
        )->queryAll(false);

        $this->_modelMap = array();
        $this->_permalinkMap = array();

        foreach ($items as $item) {
            $model = $this->map($item[0], $item[1]);
            $this->_modelMap[$item[2]] = $model;
            $this->_permalinkMap[$model] = array('permalink' => $item[2], 'version' => $item[3]);
        }
    }

    public function hasPermalinkRaw($model, $id)
    {
        return $this->getPermalinkRaw($model, $id) !== null;
    }

    /**
     * @param  CActiveRecord $model
     * @return boolean              whether specific model has permalink
     */
    public function hasPermalink(CActiveRecord $model)
    {
        return $this->getPermalink($model) !== null;
    }

    /**
     * @param  string  $value permalink
     * @return boolean        whether permalink is attached to a model
     */
    public function hasModel($value)
    {
        return isset($this->_modelMap[$value]);
    }

    public function getPermalinkRaw($model, $id)
    {
        $action = $this->map($model, $id);

        return isset($this->_permalinkMap[$action]) ? $this->_permalinkMap[$action]['permalink'] : null;
    }

    public function setPermalinkRaw($model, $id, $permalink)
    {
        if (!Permalink::isValid($permalink))
            throw new PermalinkException(Yii::t(
                'permalink', 
                'Permalink "{permalink}" contains invalid symbols. Use Permalink::make().',
                array('{permalink}' => $permalink)));

        $hash = $this->map($model, $id);
        $permalink = trim($permalink, '/');

        $version = 0;
        if (isset($this->_permalinkMap[$hash])) {
            $permalinkData = $this->_permalinkMap[$hash];

            // Check whether current permalink hasn't changed. 
            // We simply return if it hasn't
            if ($permalinkData['permalink'] == $permalink)
                return false;

            $version = $permalinkData['version'];

            if ($this->versioning)
                ++$version;
        }

        if (isset($this->_modelMap[$permalink]) && $this->_modelMap[$permalink] !== $hash) {
            throw new PermalinkExistsException(Yii::t(
                'permalink', 
                'Permalink "{permalink}" already exists.', 
                array('{permalink}' => $permalink)
            ));
        }

        if ($this->getDbConnection()->createCommand('
            REPLACE INTO {{permalinks}} (model, model_id, permalink, version) 
            VALUES(:model, :id, :permalink, :ver)')->execute(array(
                ':model'    => $model, 
                ':id'       => $id,
                ':permalink'=> $permalink,
                ':ver'      => $version))) 
        {
            $this->_modelMap[$permalink] = $hash;
            $this->_permalinkMap[$hash] = array('permalink' => $permalink, 'version' => $version);

            return true;
        }

        return false;
    }

    public function setPermalink(CActiveRecord $model, $permalink)
    {
        if (!$pk = $model->getPrimaryKey())
            throw new CException(Yii::t('permalink', 'Cannot save permalink for record that does not have a primary key.'));

        return $this->setPermalinkRaw(get_class($model), $model->getPrimaryKey(), $permalink);
    }

    public function getPermalink(CActiveRecord $model)
    {
        return $this->getPermalinkRaw(get_class($model), $model->getPrimaryKey());
    }

    public function getModelRaw($permalink)
    {
        return isset($this->_modelMap[$permalink])
            ? $this->unmap($this->_modelMap[$permalink])
            : null;
    }

    public function getModel($permalink)
    {
        if ($model = $this->getModelRaw($permalink)) {
            return CActiveRecord::model($model[0])->findByPk($model[1]);
        }

        return null;
    }

    public function removePermalinksRaw($name, $id, $old = false)
    {
        $hash = $this->map($name, $id);

        if (!isset($this->_permalinkMap[$hash])) {
            return;
        }

        $currentVersion = $this->_permalinkMap[$hash]['version'];

        $command = $this->getDbConnection()->createCommand('
            DELETE
            FROM {{permalinks}}
            WHERE model = :model AND model_id = :id'.($old ? ' AND version < :ver' : '')
        );

        $params = array(
            'model' => $name,
            'id' => $id,
        );

        if ($old) {
            $params['ver'] = $currentVersion;
        }

        if (($removed = $command->execute($params)) && !$old)
            unset($this->_permalinkMap[$hash]);

        return $removed;
    }

    public function removePermalinks(CActiveRecord $model)
    {
        return $this->removePermalinksRaw(get_class($model), $model->getPrimaryKey());
    }

    /**
     * Removes specific permalink.
     * 
     * Helpful when there is some collisions in naming. Should be avoided in any costs.
     * 
     * WARNING: removing active permalink of the model will erace the history
     *         
     * @param  string $permalink the permalink
     * @return int            number of removed permalinks
     */
    public function removePermalink($permalink)
    {
        if (!isset($this->_modelMap[$permalink]))
            throw new PermalinkException(Yii::t(
                'permalink', 
                'Permalink "{permalink}" does not exists.', 
                array('{permalink}' => $permalink)
            ));

        $currentPermalink = $this->_permalinkMap[$this->_modelMap[$permalink]]['permalink'];

        if ($currentPermalink == $permalink) {
            list($model, $id) = $this->unmap($this->_modelMap[$permalink]);
            return $this->removePermalinksRaw($model, $id);
        }

        $removed = $this->getDbConnection()->createCommand('
            DELETE
            FROM {{permalinks}}
            WHERE permalink = :val
            LIMIT 1'
        )->execute(array('val' => $permalink));

        // No need for this since it valuable for url parsing only which is done already
        // if ($removed)
        //     unset($this->_modelMap[$permalink]);

        return $removed;
    }

    public function clearHistoryRaw($name, $id)
    {
        return $this->removePermalinksRaw($name, $id, true);
    }

    public function clearHistory(CActiveRecord $model)
    {
        return $this->removePermalinksRaw(get_class($model), $model->getPrimaryKey(), true);
    }

    /////////////////////
    // Private Members //
    /////////////////////

    /**
     * Maps model name and model id into string
     * @param  string $model 
     * @param  int $id    
     * @return string        
     */
    private function map($model, $id)
    {
        return $model.'/'.$id;
    }

    public function unmap($value)
    {
        return explode('/', $value);
    }

    ////////////////
    // Properties //
    ////////////////

    public function getDbConnection()
    {
        if ($this->_db !== null) {
            return $this->_db;
        }

        if (isset($this->connectionId) && ($db = Yii::app()->getComponent($this->connectionId)) && $db instanceof CDbConnection) {
            return $this->_db = $db;
        }

        throw new CException(Yii::t('app', 'Specify valid database connection id.'));
    }
}