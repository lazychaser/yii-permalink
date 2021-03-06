<?php

/**
 * @package permalink
*/
class PermalinkRule extends CBaseUrlRule
{
    const IGNORE = 'ignore';
    const REDIRECT = 'redirect';
    const SHOW = 'show';

    /**
     * The action to perform when user accesses legacy permalink.
     * ignore - forward url parsing to url manager (usually this will cause an 404 error)
     * redirect - redirect to active permalink with 301 Moved Permanently status
     * show - show content (not recomended since content will be available from different URLs
     *     which crawlers won't like)
     * @var string
     */
    public $old = self::IGNORE;

    /**
     * Array of modelClassName => route values, i.e.
     * ProductCatalog => shop/catalog/view
     * @var array
     */
    public $map = array();

    /**
     * array(modelClassName, id)
     * The model that is used to represent index page.
     * This model will be viewed when no route is requested.
     * Empty URL will be created for this model.
     *
     * @var array
     */
    public $indexPage;

    private $_manager;
    private $_routeMap;

    public function createUrl($manager, $route, $params, $ampersand)
    {
        if (!isset($params['id'])) {
            return false;
        }

        $id = $params['id'];
        unset($params['id']);

        if (($modelName = $this->getModelByRoute($route)) === false) {
            return false;
        }

        if (is_array($this->indexPage) && $modelName === $this->indexPage[0] && $id == $this->indexPage[1]) {
            $url = '';
        } else {
            if (!$url = $this->getManager()->getPermalinkRaw($modelName, $id)) {
                return false;
            }

            if ($manager->urlSuffix) {
                $url .= $manager->urlSuffix;
            }
        }

        if (!empty($params) && $pathInfo = $manager->createPathInfo($params, '=', $ampersand))
            $url .= '?'.$pathInfo;

        return $url;
    }

    public function parseUrl($manager, $request, $pathInfo, $rawPathInfo)
    {
        $pathInfo = trim($pathInfo, '/');
        
        if (empty($pathInfo) && is_array($this->indexPage)) {
            $model = $this->indexPage;
            $isIndex = true;
        } elseif (empty($pathInfo) || !$model = $this->getManager()->getModelRaw($pathInfo)) {
            return false;
        }

        list($modelName, $id) = $model;

        if (!isset($isIndex)) {
            $currentPermalink = $this->getManager()->getPermalinkRaw($modelName, $id);

            // Check whether permalink is old
            if ($currentPermalink != $pathInfo) {
                switch ($this->old) {
                    case self::IGNORE: return false;
                    case self::REDIRECT: 
                        $request = Yii::app()->getRequest();
                        
                        if ($queryString = $request->getQueryString())
                            $queryString = '?'.$queryString;

                    $request->redirect($manager->getBaseUrl().'/'.$currentPermalink.$queryString, true, 301);

                    case self::SHOW: break; // simply continue execution and show content
                }
            }
        }

        $_REQUEST['id'] = $_GET['id'] = $id;

        return $this->getRouteByModel($modelName);
    }

    ////////////////
    // Properties //
    ////////////////

    public function getManager()
    {
        if ($this->_manager == null) {
            if (!$this->_manager = Yii::app()->getComponent('permalinkManager')) {
                throw new CException(Yii::t(
                    'permalink', 
                    'Cannot find permalink manager component. Make shure it is available under id of "permalinkManager".')
                );
            }
        }

        return $this->_manager;
    }

    /**
     * Returns model class name from given route.
     * At first, {@link map} will be scanned.
     * If it fails, for routes like "user/view" "User" will be returned.
     *
     * @param  string $route The route.
     *
     * @return string        Model class name or false.
     */
    protected function getModelByRoute($route)
    {
        if ($this->_routeMap === null) {
            $this->_routeMap = array();

            foreach ($this->map as $key => $value) {
                $this->_routeMap[$value] = $key;
            }
        }

        if (isset($this->_routeMap[$route]))
            return $this->_routeMap[$route];

        list($modelName, $action) = explode('/', $route);

        if ($action === $this->getManager()->action)
            return ucfirst($modelName);

        return false;
    }

    /**
     * Gets route for viewing model.
     *
     * @param  string $modelClassName 
     *
     * @return string                 
     */
    protected function getRouteByModel($modelClassName)
    {
        if (isset($this->map[$modelClassName]))
            return $this->map[$modelClassName];

        return lcfirst($modelClassName.'/'.$this->getManager()->action);
    }
}