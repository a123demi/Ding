<?php
/**
 * A mapper implementation for http requests.
 *
 * PHP Version 5
 *
 * @category   Ding
 * @package    Mvc
 * @subpackage Http
 * @author     Marcelo Gornstein <marcelog@gmail.com>
 * @license    http://marcelog.github.com/ Apache License 2.0
 * @link       http://marcelog.github.com/
 *
 * Copyright 2011 Marcelo Gornstein <marcelog@gmail.com>
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *     http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 *
 */
namespace Ding\Mvc\Http;

use Ding\Logger\ILoggerAware;
use Ding\Container\IContainer;
use Ding\Container\IContainerAware;
use Ding\Container\Impl\ContainerImpl;
use Ding\Mvc\IMapper;
use Ding\Mvc\Action;

/**
 * A mapper implementation for http requests.
 *
 * PHP Version 5
 *
 * @category   Ding
 * @package    Mvc
 * @subpackage Http
 * @author     Marcelo Gornstein <marcelog@gmail.com>
 * @license    http://marcelog.github.com/ Apache License 2.0
 * @link       http://marcelog.github.com/
 */
class HttpUrlMapper implements IMapper, IContainerAware, ILoggerAware
{
    /**
     * log4php logger or our own.
     * @var Logger
     */
    private $_logger;

    /**
     * @var Controller[]
     */
    private $_map;

    /**
     * Used from the Mvc driver to setup annotated controllers.
     * @var string[]
     */
    private static $_annotatedControllers = array();

    /**
     * Used from the Mvc driver to add controllers found by annotations.
     *
     * @param string $url        Url mapped.
     * @param string $controller Name for the bean (autogenerated).
     *
     * @return void
     */
    public static function addAnnotatedController($url, $controller)
    {
        self::$_annotatedControllers[] = array($url, $controller);
    }

    /**
     * Sets the map for this mapper.
     *
     * @param array[] $map An array containing arrays defined like this:
     * [0] => IAction, [1] => IController
     *
     * (non-PHPdoc)
     * @see Ding\Mvc.IMapper::setMap()
     *
     * @return void
     */
    public function setMap(array $map)
    {
        $this->_map = $map;
    }
    /**
     * (non-PHPdoc)
     * @see Ding\Logger.ILoggerAware::setLogger()
     */
    public function setLogger(\Logger $logger)
    {
        $this->_logger = $logger;
    }

    /**
     * (non-PHPdoc)
     * @see Ding\Container.IContainerAware::setContainer()
     */
    public function setContainer(IContainer $container)
    {
        $this->container = $container;
    }

    /**
     * This will map a full url, like /A/B/C to an HttpAction and will try to
     * find a controller that can handle it. This will isolate the baseUrl.
     *
     * @param Action $action Original action (coming from the frontcontroller,
     * the full url).
     *
     * @return array [0] => Controller [1] => Method to call (With
     * 'Action' appended to the end of the method name).
     */
    public function map(Action $action)
    {
        $url = $action->getId();
        // Add a slash to the beginning is none is found after removing the
        // base url.
        if ($url[0] != '/') {
            $url = '/' . $url;
        }
        // Do not take into account the arguments part of the url.
        $url = explode('?', $url);
        $url = $url[0];

        // Add a trailing slash to the result.
        $len = strlen($url) - 1;
        if ($url[$len] != '/') {
            $url .= '/';
        }

        $this->_logger->debug('Trying to match: ' . $url);
        // Lookup a controller that can handle this url.
        $try = array_merge($this->_map, self::$_annotatedControllers);
        $candidates = array();
        foreach ($try as $map) {
            $urls = $map[0];
            if (!is_array($urls)) {
                $urls = array($urls);
            }
            $controller = $map[1];
            foreach ($urls as $controllerUrl) {
                if ($controllerUrl[0] != '/') {
                    $controllerUrl = '/' . $controllerUrl;
                }
                $len = strlen($controllerUrl);
                if ($controllerUrl[$len - 1] != '/') {
                    $controllerUrl = $controllerUrl . '/';
                }
                $controllerUrlStart = strpos($url, $controllerUrl);
                if (!($controllerUrlStart === 0)) {
                    continue;
                }
                $start = $controllerUrlStart + strlen($controllerUrl);
                $action = substr($url, $start);
                if ($action === false) {
                    $action = 'Main';
                }
                $action = explode('/', $action);
                $action = $action[0];
                if (!is_object($controller)) {
                    $controller = $this->container->getBean($controller);
                    $this->_logger->debug(
                    	"Found as annotated controller: "
                    	. "$controllerUrl in " . get_class($controller)
                    );
                }
                if (!isset($candidates[$len])) {
                    $candidates[$len] = array();
                }
                $candidates[$len][] = array($controller, $action . 'Action');
            }
        }
        if (empty($candidates)) {
            return false;
        }
        krsort($candidates);
        $controllers = array_shift($candidates);
        return array_shift($controllers);
    }

    /**
     * Constructor.
     *
     * @return void
     */
    public function __construct()
    {
        $this->_map = array();
    }
}