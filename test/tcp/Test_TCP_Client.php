<?php
declare(ticks=1);
$mockSocketCreate = false;
$mockSocketSelect = false;

/**
 * This class will test the TCP Client.
 *
 * PHP Version 5
 *
 * @category   Ding
 * @package    Test
 * @subpackage Tcp
 * @author     Marcelo Gornstein <marcelog@gmail.com>
 * @license    http://marcelog.github.com/ Apache License 2.0
 * @version    SVN: $Id$
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
use Ding\Container\Impl\ContainerImpl;
use Ding\Helpers\TCP\ITCPClientHandler;
use Ding\Helpers\TCP\ITCPServerHandler;

/**
 * This class will test the TCP Client.
 *
 * PHP Version 5
 *
 * @category   Ding
 * @package    Test
 * @subpackage Tcp
 * @author     Marcelo Gornstein <marcelog@gmail.com>
 * @license    http://marcelog.github.com/ Apache License 2.0
 * @link       http://marcelog.github.com/
 */
class Test_TCP_Client extends PHPUnit_Framework_TestCase
{
    private $_properties = array();

    public function setUp()
    {
        global $mockSocketCreate;
        global $mockSocketSelect;
        $mockSocketCreate = false;
        $mockSocketSelect = false;
        $this->_properties = array(
            'ding' => array(
                'log4php.properties' => RESOURCES_DIR . DIRECTORY_SEPARATOR . 'log4php.properties',
                'cache' => array(),
                'factory' => array(
                    'bdef' => array(
                        'xml' => array(
                        	'filename' => 'tcpclient.xml', 'directories' => array(RESOURCES_DIR)
                        )
                    )
                )
            )
        );
    }

    /**
     * @test
     * @expectedException Ding\Helpers\TCP\Exception\TCPException
     */
    public function cannot_bind()
    {
        $container = ContainerImpl::getInstance($this->_properties);
        $client = $container->getBean('Client');
        $client->open('1.1.1.1', 1);
    }

    /**
     * @test
     * @expectedException Ding\Helpers\TCP\Exception\TCPException
     */
    public function cannot_connect()
    {
        $container = ContainerImpl::getInstance($this->_properties);
        $client = $container->getBean('Client3');
        $client->open('127.0.0.1', rand(2000, 65535));
    }

    /**
     * @test
     */
    public function can_timeout_on_connect()
    {
        $container = ContainerImpl::getInstance($this->_properties);
        $client = $container->getBean('Client');
        $start = time();
        $client->open();
        while (MyClientHandler::$time < 1) {
            usleep(1000);
        }
        $this->assertEquals(MyClientHandler::$time - $start, 10);
    }

    /**
     * @test
     */
    public function can_connect_and_receive_nonblocking()
    {
        $container = ContainerImpl::getInstance($this->_properties);
        $client = $container->getBean('Client2');
        $client->open();
        while (strlen(MyClientHandler::$data) < 1) {
            usleep(1000);
        }
        $this->assertContains('Set-Cookie', MyClientHandler::$data);
    }
    /**
     * @test
     */
    public function can_connect_and_receive_blocking()
    {
        $container = ContainerImpl::getInstance($this->_properties);
        $client = $container->getBean('Client4');
        $client->open();
        while (strlen(MyClientHandler::$data) < 1) {
            usleep(1000);
        }
        $this->assertContains('Set-Cookie', MyClientHandler::$data);
    }

    /**
     * @test
     */
    public function can_timeout_on_starving_reading()
    {
        $container = ContainerImpl::getInstance($this->_properties);
        $client = $container->getBean('Client5');
        $start = time();
        $client->open();
        while (MyClientHandler::$time < 1) {
            usleep(1000);
        }
        $this->assertEquals(MyClientHandler::$time - $start, 10);
    }
    /**
     * @test
     */
    public function can_close_on_server_disconnect()
    {
        $container = ContainerImpl::getInstance($this->_properties);
        $server = $container->getBean('Server');
        $server->open();
        MyServerHandler::doClient($container->getBean('Client6'));
        while (strlen(MyServerHandler666::$data) < 1) {
            usleep(1000);
        }
        $this->assertEquals(MyServerHandler666::$data, "disconnect");
        $server->close();
    }
}

class MyClientHandler implements ITCPClientHandler
{
    public static $time;
    protected $client;
    public static $data;

    public function connectTimeout()
    {
        self::$time = time();
    }

    public function readTimeout()
    {
        self::$time = time();
    }
    public function beforeConnect()
    {
    }

    public function connect()
    {
        $this->client->write("GET / HTTP/1.1\nhost:www.google.com\n\n");
    }

    public function disconnect()
    {
    }

    public function setClient(\Ding\Helpers\TCP\TCPClientHelper $client)
    {
        $this->client = $client;
    }

    public function data()
    {
        $buffer = '';
        $len = 4096;
        $this->client->read($buffer, $len);
        self::$data = $buffer;
        $this->client->close();
    }
}
class MyClientHandler666 implements ITCPClientHandler
{
    public static $time;
    protected $client;
    public static $data;

    public function connectTimeout()
    {
        self::$time = time();
    }

    public function readTimeout()
    {
        self::$time = time();
    }
    public function beforeConnect()
    {
    }

    public function connect()
    {
    }

    public function disconnect()
    {
    }

    public function setClient(\Ding\Helpers\TCP\TCPClientHelper $client)
    {
        $this->client = $client;
    }

    public function data()
    {
        $buffer = '';
        $len = 4096;
        $this->client->read($buffer, $len);
        self::$data = $buffer;
        $this->client->close();
    }
}
class MyServerHandler666 implements ITCPServerHandler
{
    public static $data;
    protected $server;

    public function setServer(\Ding\Helpers\TCP\TCPServerHelper $server)
    {
        $this->server = $server;
    }

    public function beforeOpen()
    {
    }

    public function beforeListen()
    {
    }

    public function close()
    {
    }

    public function handleConnection($remoteAddress, $remotePort)
    {
        //$this->server->write($remoteAddress, $remotePort, "Hi!\n");
        $this->server->disconnect($remoteAddress, $remotePort);
    }

    public function readTimeout($remoteAddress, $remotePort)
    {
        self::$data = 'timeout';
    }

    public function handleData($remoteAddress, $remotePort)
    {
        $buffer = '';
        $len = 1024;
        //$this->server->read($remoteAddress, $remotePort, $buffer, $len);
        self::$data = $buffer;
    }

    public static function doClient($client)
    {
        $client->open();
        sleep(2);
    }

    public function disconnect($remoteAddress, $remotePort)
    {
        self::$data = 'disconnect';
    }
}