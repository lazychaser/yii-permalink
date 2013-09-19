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
    const CACHE_KEY = 'permalinks';

    /**
     * Default action id used to display specific model.
     *
     * @var string
     */
    public $action = 'view';

    /**
     * Database connection ID used to store permalink records.
     *
     * @var string
     */
    public $connectionId = 'db';

    /**
     * The cache component id.
     *
     * @var string
     */
    public $cacheId = 'cache';

    /**
     * Whether to enable history.
     * If history is enabled, old permalinks for models are stored
     * and they stay active.
     *
     * @var boolean
     */
    public $enableHistory = true;

    // permalink => model/model_id
    private $_modelMap;

    // model/model_id => array(permalink, version)
    private $_permalinkMap;
    
    private $_db;
    private $_cache;
    private $_updateCache;

    public function init()
    {
        if (!$this->fromCache()) {
            $this->prefetchData();
            $this->updateCache();
        }

        Yii::app()->onEndRequest = array($this, 'handleEndRequest');
    }

    /**
     * Try to load data from cache.
     *
     * @return  boolean
     */
    protected function fromCache()
    {
        if (!$cache = $this->getCache()) {
            return false;
        }

        if (($data = $cache->get(self::CACHE_KEY)) === false) {
            return false;
        }

        list($this->_modelMap, $this->_permalinkMap) = $data;

        return true;
    }

    /**
     * Load data from database.
     *
     * @return  void
     */
    protected function prefetchData()
    {
        $db = $this->getDbConnection();

        // We need items to be sorted by version.
        // But we don't need to set ORDER BY explicitly since new records are 
        // inserted via REPLACE INTO and newest version will recieve highest 
        // primary key.
        $command = $db->createCommand();
        $command
            ->select('model, model_id, permalink, version')
            ->from('{{permalinks}}');

        $this->_modelMap = array();
        $this->_permalinkMap = array();

        foreach ($command->queryAll(false) as $item) {
            $model = $this->map($item[0], $item[1]);
            $this->_modelMap[$item[2]] = $model;
            $this->_permalinkMap[$model] = array($item[2], $item[3]);
        }
    }

    /**
     * Signal that cache needs update.
     *
     * @return  void
     */
    protected function updateCache()
    {
        $this->_updateCache = true;
    }

    /**
     * Get whether permalink is exists for specific model instance.
     *
     * @param   string   $model
     * @param   integer   $id
     *
     * @return  boolean
     */
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
     * Get whether permalink is attached to a model.    
     * 
     * @param  string  $value
     * @return boolean
     */
    public function hasModel($value)
    {
        return $this->getModelRaw($value) !== null;
    }

    /**
     * Get permalink for raw model instance given model class name and model id.
     *
     * @param   string  $model
     * @param   integer  $id
     *
     * @return  string|null
     */
    public function getPermalinkRaw($model, $id)
    {
        $key = $this->map($model, $id);

        return isset($this->_permalinkMap[$key]) ? $this->_permalinkMap[$key][0] : null;
    }

    /**
     * Set permalink for model.
     *
     * @param string $className     Model class name.
     * @param int $id        Model instance id.
     * @param string $permalink Valid permalink.
     *
     * @return  boolean Whether permalink has changed.
     * 
     * @throws PermalinkException If $permalink is invalid
     * @throws PermalinkExistsException If $permalink belongs to other model.
     */
    public function setPermalinkRaw($className, $id, $permalink)
    {
        if (!Permalink::isValid($permalink)) {
            throw new PermalinkException(Yii::t(
                'permalink', 
                'Permalink "{permalink}" contains invalid symbols. Use Permalink::make().',
                array('{permalink}' => $permalink)));
        }

        $key = $this->map($className, $id);
        $permalink = trim($permalink, '/');

        $version = 0;
        if (isset($this->_permalinkMap[$key])) {
            list($currentPermalink, $version) = $this->_permalinkMap[$key];

            // Check whether current permalink hasn't changed. 
            // We simply return if it hasn't
            if ($currentPermalink === $permalink) {
                return false;
            }

            if ($this->enableHistory) {
                ++$version;
            }
        }

        if (isset($this->_modelMap[$permalink]) && $this->_modelMap[$permalink] !== $key) {
            throw new PermalinkExistsException(Yii::t(
                'permalink', 
                'Permalink "{permalink}" already exists.', 
                array('{permalink}' => $permalink)
            ));
        }

        if ($this->getDbConnection()->createCommand('
            REPLACE INTO {{permalinks}} (model, model_id, permalink, version) 
            VALUES(:model, :id, :permalink, :ver)')->execute(array(
                ':model'    => $className, 
                ':id'       => $id,
                ':permalink'=> $permalink,
                ':ver'      => $version))) 
        {
            $this->_modelMap[$permalink] = $key;
            $this->_permalinkMap[$key] = array($permalink, $version);
            $this->updateCache();

            return true;
        }

        return false;
    }

    /**
     * Sets permalink for model.
     *
     * @param CActiveRecord $model     The model instance.
     * @param string        $permalink The permalink.
     *
     * @return  boolean whether permalinks has changed.
     *
     * @throws PermalinkException If $permalink is invalid
     * @throws CException If $model doesn't have a primary key.
     */
    public function setPermalink(CActiveRecord $model, $permalink)
    {
        if (!$pk = $model->getPrimaryKey()) {
            throw new CException(Yii::t('permalink', 'Cannot save permalink for record that does not have a primary key.'));
        }

        return $this->setPermalinkRaw(get_class($model), $pk, $permalink);
    }

    /**
     * Get permalink of model instance.
     *
     * @param   CActiveRecord  $model
     *
     * @return  string
     */
    public function getPermalink(CActiveRecord $model)
    {
        return $this->getPermalinkRaw(get_class($model), $model->getPrimaryKey());
    }

    /**
     * Get model class name and model id from given permalink string.
     *
     * @param   string  $permalink
     *
     * @return  array
     */
    public function getModelRaw($permalink)
    {
        return isset($this->_modelMap[$permalink])
            ? $this->unmap($this->_modelMap[$permalink])
            : null;
    }

    /**
     * Get model instance from permalink.
     *
     * @param  string $permalink The permalink.
     *
     * @return CActiveRecord|null
     */
    public function getModel($permalink)
    {
        if ($model = $this->getModelRaw($permalink)) {
            return CActiveRecord::model($model[0])->findByPk($model[1]);
        }

        return null;
    }

    /**
     * Remove permalinks of specific model.
     * If historyOnly is set to true only old permalinks are removed and current
     * permalink is kept.
     *
     * @param  string  $className
     * @param  integer $id
     * @param  boolean $historyOnly
     *
     * @return int               number of removed permalinks.
     */
    public function removePermalinksRaw($className, $id, $historyOnly = false)
    {
        $key = $this->map($className, $id);

        if (!isset($this->_permalinkMap[$key])) {
            return 0;
        }

        $currentVersion = $this->_permalinkMap[$key][1];

        $command = $this->getDbConnection()->createCommand('
            DELETE
            FROM {{permalinks}}
            WHERE model = :model AND model_id = :id'.($historyOnly ? ' AND version < :ver' : '')
        );

        $params = array(
            'model' => $className,
            'id' => $id,
        );

        if ($historyOnly) {
            $params['ver'] = $currentVersion;
        }

        if (($removed = $command->execute($params)) && !$historyOnly) {
            unset($this->_permalinkMap[$key]);
            $this->updateCache();
        }

        return $removed;
    }

    /**
     * Removes permalinks of model.
     *
     * @param  CActiveRecord $model the model instance
     *
     * @return int               number of removed permalinks.
     */
    public function removePermalinks(CActiveRecord $model)
    {
        return $this->removePermalinksRaw(get_class($model), $model->getPrimaryKey(), false);
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
     *
     * @throws PermalinkException If permalink doesn't exists.
     */
    public function removePermalink($permalink)
    {
        if (!isset($this->_modelMap[$permalink])) {
            throw new PermalinkException(Yii::t(
                'permalink', 
                'Permalink "{permalink}" does not exists.', 
                array('{permalink}' => $permalink)
            ));
        }

        // Check if are trying to remove current permalink.
        // If so we will remove all permalinks.
        $currentPermalink = $this->_permalinkMap[$this->_modelMap[$permalink]][0];
        if ($currentPermalink === $permalink) {
            list($model, $id) = $this->unmap($this->_modelMap[$permalink]);
            return $this->removePermalinksRaw($model, $id);
        }

        // Otherwise remove only record from database.
        $removed = $this->getDbConnection()->createCommand('
            DELETE
            FROM {{permalinks}}
            WHERE permalink = :val
            LIMIT 1'
        )->execute(array('val' => $permalink));

        return $removed;
    }

    /**
     * Remove old permalinks of raw model instance.
     *
     * @param  string $className The model class name.
     * @param  int $id   The model instance id.
     *
     * @return int       number of removed permalinks.
     */
    public function clearHistoryRaw($className, $id)
    {
        return $this->removePermalinksRaw($className, $id, true);
    }

    /**
     * Remove old permalinks of model.
     *
     * @param  CActiveRecord $model The model instance.
     *
     * @return int               number of removed permalinks.
     */
    public function clearHistory(CActiveRecord $model)
    {
        return $this->removePermalinksRaw(get_class($model), $model->getPrimaryKey(), true);
    }

    /////////////////////
    // Private Members //
    /////////////////////

    /**
     * Maps model name and model id into string.
     * 
     * @param  string  $model 
     * @param  integer $id    
     * 
     * @return string        
     */
    private function map($model, $id)
    {
        return $model.'/'.$id;
    }

    /**
     * Converts mapped model to the class name and id.
     *
     * @param   string  $value
     *
     * @return  array
     */
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

    public function getCache()
    {
        if ($this->_cache === null && $this->cacheId) {
            if (!$this->_cache = Yii::app()->getComponent($this->cacheId)) {
                throw new CException(Yii::t('app', 'Specify valid cache component id.'));
            }
        }

        return $this->_cache;
    }

    ////////////
    // Events //
    ////////////

    /**
     * Handle the end of the request.
     *
     * @return  void
     */
    public function handleEndRequest($evt)
    {
        if ($this->_updateCache && $cache = $this->getCache()) {
            $cache->set(self::CACHE_KEY, array(
                $this->_modelMap,
                $this->_permalinkMap,
            ));

            $this->_updateCache = false;
        }
    }
}