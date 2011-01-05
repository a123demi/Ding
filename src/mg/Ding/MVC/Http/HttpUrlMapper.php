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
 * @license    http://www.noneyet.ar/ Apache License 2.0
 * @version    SVN: $Id$
 * @link       http://www.noneyet.ar/
 */
namespace Ding\MVC\Http;

use Ding\MVC\Exception\MVCException;
use Ding\MVC\IMapper;
use Ding\MVC\Action;

/**
 * A mapper implementation for http requests.
 *
 * PHP Version 5
 *
 * @category   Ding
 * @package    Mvc
 * @subpackage Http
 * @author     Marcelo Gornstein <marcelog@gmail.com>
 * @license    http://www.noneyet.ar/ Apache License 2.0
 * @version    SVN: $Id$
 * @link       http://www.noneyet.ar/
 */
class HttpUrlMapper implements IMapper
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
     * Assigned base url.
     * @var string
     */
    private $_baseUrl;

    /**
     * Sets the map for this mapper.
     *
     * @param array[] $map An array containing arrays defined like this:
     * [0] => IAction, [1] => IController
     *
     * (non-PHPdoc)
     * @see Ding\MVC.IMapper::setMap()
     *
     * @return void
     */
    public function setMap(array $map)
    {
        $this->_map = $map;
    }

    /**
     * Sets the base url for this mapper.
     *
     * @param string $baseUrl Base url.
     *
     * @return void
     */
    public function setBaseUrl($baseUrl)
    {
        $this->_baseUrl = $baseUrl;
    }

    /**
     * Returns the base url for this mapper.
     *
     * @return string
     */
    public function getBaseUrl()
    {
        return $this->_baseUrl;
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
        $urlStart = strpos($url, $this->_baseUrl);
        if ($this->_logger->isDebugEnabled()) {
            $this->_logger->debug('Trying to match: ' . $url);
        }
        if ($urlStart === false || $urlStart > 0) {
            throw new MVCException('Not a base url.');
        }
        // Add a slash to the beginning is none is found after removing the
        // base url.
        if ($url[0] != '/') {
            $url = '/' . $url;
        }
        // Do not take into account the arguments part of the url.
        $url = explode('?', substr($url, $urlStart + strlen($this->_baseUrl)));
        $url = $url[0];

        // Add a trailing slash to the result.
        $len = strlen($url) - 1;
        if ($url[$len] != '/') {
            $url .= '/';
        }

        if ($this->_logger->isDebugEnabled()) {
            $this->_logger->debug('Trying to match: ' . $url);
        }
        // Lookup a controller that can handle this url.
        foreach ($this->_map as $map) {
            $controllerUrl = $map[0];
            $controller = $map[1];
            if ($controllerUrl[0] != '/') {
                $controllerUrl = '/' . $controllerUrl;
            }
            $len = strlen($controllerUrl);
            if ($controllerUrl[$len - 1] != '/') {
                $controllerUrl = $controllerUrl . '/';
            }
            $controllerUrlStart = strpos($url, $controllerUrl);
            if ($controllerUrlStart === false || $controllerUrlStart > 0) {
                continue;
            }
            $start = $controllerUrlStart + strlen($controllerUrl);
            $action = substr($url, $start);
            if ($action === false) {
                $action = 'Main';
            }
            $action = explode('/', $action);
            $action = $action[0];
            return array($controller, $action . 'Action');
        }
        return false;
    }

    /**
     * Constructor.
     *
     * @return void
     */
    public function __construct()
    {
        $this->_logger = \Logger::getLogger('Ding.MVC');
        $this->_map = array();
        $this->_baseUrl = '/';
    }
}