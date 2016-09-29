<?php

/*
 * Theme Manager for Yii2
 *
 * @link      https://github.com/hiqdev/yii2-thememanager
 * @package   yii2-thememanager
 * @license   BSD-3-Clause
 * @copyright Copyright (c) 2015-2016, HiQDev (http://hiqdev.com/)
 */

namespace hiqdev\thememanager;

use ReflectionClass;
use Yii;
use yii\helpers\ArrayHelper;

/**
 * Theme class.
 */
class Theme extends \yii\base\Theme implements \hiqdev\yii2\collection\ItemWithNameInterface
{
    use GetManagerTrait;

    /**
     * @var string theme name
     */
    public $name;

    /**
     * @var string theme label
     */
    public $label;

    /**
     * @var array assets to be registered for this theme
     */
    public $assets = [];

    private $_view;

    /**
     * Returns the view object that can be used to render views or view files.
     * The [[render()]] and [[renderFile()]] methods will use
     * this view object to implement the actual view rendering.
     * If not set, it will default to the "view" application component.
     *
     * @return \yii\web\View the view object that can be used to render views or view files
     */
    public function getView()
    {
        if ($this->_view === null) {
            $this->_view = $this->getManager()->getView();
        }

        return $this->_view;
    }

    /**
     * Sets the view object to be used.
     *
     * @param View $view the view object that can be used to render views or view files
     */
    public function setView($view)
    {
        $this->_view = $view;
    }

    public $pathMap = [
        __DIR__ . '/widgets/views' => '$themedWidgetPaths',
        '$themedWidgetPaths' => '$themedViewPaths/widgets',
    ];

    /**
     * Getter for pathMap.
     */
    public function init()
    {
        parent::init();
        if (!is_array($this->pathMap)) {
            $this->pathMap = [];
        }

        $this->pathMap = $this->compilePathMap(ArrayHelper::merge([
            '$themedViewPaths' => $this->buildThemedViewPaths(),
            Yii::$app->viewPath => '$themedViewPaths',
        ], $this->getManager()->pathMap, $this->pathMap));
    }

    public function compilePathMap($map)
    {
        $map = $this->substituteVars($map);

        $res = [];
        foreach ($map as $from => &$tos) {
            $tos = array_reverse(array_unique(array_values($tos)));
            foreach ($tos as &$to) {
                $to = Yii::getAlias($to);
            }
            $res[Yii::getAlias($from)] = $tos;
        }

        return $res;
    }

    public function substituteVars($vars)
    {
        $proceed = true;
        while ($proceed) {
            $proceed = false;
            foreach ($vars as $key => $exp) {
                if ($this->isVar($exp)) {
                    $value = $this->calcExp($exp, $vars);
                    if (isset($value)) {
                        $vars[$key] = $value;
                        $proceed = true;
                    }
                }
            }
        }

        foreach (array_keys($vars) as $key) {
            if ($this->isVar($key)) {
                unset($vars[$key]);
            }
        }

        return $vars;
    }

    public function isVar($name)
    {
        return is_string($name) && (strncmp($name, '$', 1) === 0);
    }

    public function calcExp($exp, $vars)
    {
        $pos = strpos($exp, '/');
        if ($pos === FALSE) {
            return $vars[$exp];
        }
        list($name, $suffix) = explode('/', $exp, 2);

        return array_map(function ($a) use ($suffix) { return "$a/$suffix"; }, $vars[$name]);
    }

    public function buildThemedViewPaths()
    {
        return array_map(function ($a) { return "$a/views"; }, $this->findParentPaths());
    }

    public function findParentPaths()
    {
        $ref = $this->getReflection();
        for ($depth = 0; $depth < 10; ++$depth) {
            $dirs[] = dirname($ref->getFilename());
            $ref = $ref->getParentClass();
            if (__CLASS__ === $ref->name) {
                break;
            }
        }

        return $dirs;
    }

    protected $_reflection;

    public function getReflection()
    {
        if (!$this->_reflection) {
            $this->_reflection = new ReflectionClass($this);
        }

        return $this->_reflection;
    }

    private $_settings;

    /**
     * @param string $settings theme settings model class name or config.
     */
    public function setSettings($settings)
    {
        $this->_settings = $settings;
    }

    public function getSettings()
    {
        if (!is_object($this->_settings)) {
            if (!$this->_settings) {
                $this->_settings = static::findSettingsClass(get_called_class());
            }
            $this->_settings = Yii::createObject($this->_settings);
            $this->_settings->load();
        }

        return $this->_settings;
    }

    public static function calcSettingsClass($class)
    {
        return substr($class, 0, strrpos($class, '\\')) . '\\models\\Settings';
    }

    public static function findSettingsClass($class)
    {
        $res = static::calcSettingsClass($class);

        return class_exists($res) ? $res : static::findSettingsClass(get_parent_class($class));
    }
}
