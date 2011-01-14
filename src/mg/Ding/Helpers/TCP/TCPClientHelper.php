<?php
/**
 * TCP Client helper. You need to declare(ticks) in your own source code or
 * manually call process() in an infinite loop from your software.
 *
 * PHP Version 5
 *
 * @category   Ding
 * @package    Helpers
 * @subpackage Tcp
 * @author     Marcelo Gornstein <marcelog@gmail.com>
 * @license    http://www.noneyet.ar/ Apache License 2.0
 * @version    SVN: $Id$
 * @link       http://www.noneyet.ar/
 */
namespace Ding\Helpers\TCP;

use Ding\Helpers\TCP\Exception\TCPException;

/**
 * TCP Client helper. You need to declare(ticks) in your own source code or
 * manually call process() in an infinite loop from your software.
 *
 * PHP Version 5
 *
 * @category   Ding
 * @package    Helpers
 * @subpackage Tcp
 * @author     Marcelo Gornstein <marcelog@gmail.com>
 * @license    http://www.noneyet.ar/ Apache License 2.0
 * @link       http://www.noneyet.ar/
 */
class TCPClientHelper
{
    /**
     * Target port
     * @var integer
     */
    private $_port;

    /**
     * Target host or ip address.
     * @var string
     */
    private $_address;

    /**
     * Socket resource.
     * @var socket
     */
    private $_socket;

    /**
     * Handler for this connection (your class).
     * @var ITCPClientHandler
     */
    private $_handler;

    /**
     * Minimum needed bytes in the socket before calling data() on the handler.
     * @var integer
     */
    private $_readLen;

    /**
     * Read timeout in milliseconds.
     * @var integer
     */
    private $_rTo;

    /**
     * Connection timeout in milliseconds.
     * @var integer
     */
    private $_cTo;

    /**
     * Internal flag in order to know if the socket is connected.
     * @var boolean
     */
    private $_connected;

    private $_lastDataReadTime;

    /**
     * Call this to close the connection.
     *
     * @return void
     */
    public function close()
    {
        $this->_connected = false;
        $this->_handler->disconnect();
        socket_close($this->_socket);
        $this->_socket = false;
    }

    /**
     * Call this to read data from the server. Returns the number of bytes read.
     *
     * @param string  $buffer Where to store the read data.
     * @param integer $length Maximum length of data to read.
     * @param boolean $peek   If true, will not remove the data from the socket.
     *
     * @return integer
     */
    public function read(&$buffer, $length, $peek = false)
    {
        $length = socket_recv($this->_socket, $buffer, $length, $peek ? MSG_PEEK : 0);
        return $length;
    }

    /**
     * Call this to send data to the server. Returns the number of bytes
     * sent.
     *
     * @param string $what What to send.
     *
     * @return integer
     */
    public function write($what)
    {
        return socket_send($this->_socket, $what, strlen($what), 0);
    }

    /**
     * Call this to open the connection to the server. Will also set the
     * socket non blocking and control the connection timeout.
     *
     * @throws TCPException
     * @return void
     */
    public function open()
    {
        $this->_connected = false;
        $this->_socket = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
        if ($this->_socket === false) {
            throw new TCPException(
            	'Error opening socket: ' . socket_strerror(socket_last_error())
            );
        }
        if ($this->_cTo > 0) {
            socket_set_nonblock($this->_socket);
            $timer = 0;
        } else {
            socket_set_block($this->_socket);
            $timer = -1;
        }
        $result = false;
        $this->_handler->beforeConnect();
        for(; $timer < $this->_cTo; $timer++)
        {
            $result = @socket_connect(
                $this->_socket, $this->_address, intval($this->_port)
            );
            if ($result === true) {
                break;
            }
            $error = socket_last_error();
            if ($error != SOCKET_EINPROGRESS && $error != SOCKET_EALREADY) {
                socket_close($this->_socket);
                $error = socket_strerror(socket_last_error($this->_socket));
                $this->_socket = false;
                throw new TCPException('Could not connect: ' . $error);
            }
            // Use the select() as a sleep.
            $read = array($this->_socket);
            $write = null;
            $ex = null;
            $result = @socket_select($read, $write, $ex, 0, 1000);
        }
        if (!$result) {
            $this->_handler->connectTimeout();
            socket_close($this->_socket);
            $this->_socket = false;
            return;
        }
        socket_set_nonblock($this->_socket);
        $this->_lastDataReadTime = $this->microtime_float();
        $this->_connected = true;
        $this->_handler->connect();
    }

    /**
     * From php examples. Returns time including millseconds.
     *
     * @return float
     */
    protected function microtime_float()
    {
        list($usec, $sec) = explode(" ", microtime());
        return ((float)$usec + (float)$sec);
    }

    /**
     * Main reading loop. Call this in your own infinite loop or declare(ticks)
     * in your software. This routine will call your client handler when there
     * is data available to read. Will always detect when the other side closed
     * the connection.
     *
     * @return void
     */
    public function process()
    {
        if ($this->_socket === false || !$this->_connected) {
            return;
        }
        $read = array($this->_socket);
        $write = null;
        $ex = null;
        $result = socket_select($read, $write, $ex, 0, 1);
        if ($result === false) {
            throw new TCPException(
            	'Error selecting from socket: '
                . socket_strerror(socket_last_error($this->_socket))
            );
        }
        if ($result > 0) {
            if (in_array($this->_socket, $read)) {
                $buffer = '';
                $len = 1;
                $len = $this->read($buffer, $len, true);
                if ($len > 0) {
                    if ($len >= $this->_readLen) {
                        $this->_lastDataReadTime = $this->microtime_float();
                        $this->_handler->data();
                        return;
                    }
                } else {
                    $this->close();
                    return;
                }
            }
        }
        $now = $this->microtime_float();
        if (($now - $this->_lastDataReadTime) > $this->_rTo) {
            if ($this->_rTo > 0) {
                $this->_handler->readTimeout();
            }
            $this->_lastDataReadTime = $now;
        }
    }

    /**
     * Minimum needed bytes available in the socket before calling data() on the
     * client handler.
     *
     * @param integer $rLen Minimum data needed in socket.
     *
     * @return void
     */
    public function setReadMinLength($rLen)
    {
        $this->_readLen = intval($rLen);
    }

    /**
     * Sets the read timeout in milliseconds. 0 to disable.
     *
     * @param integer $rTo Read timeout.
     */
    public function setReadTimeout($rTo)
    {
        $this->_rTo = (float)($rTo / 1000);
    }

    /**
     * Sets connection timeout in milliseconds. 0 to disable.
     *
     * @param integer $cTo Connection timeout.
     *
     * @return void
     */
    public function setConnectTimeout($cTo)
    {
        $this->_cTo = intval($cTo);
    }

    /**
     * Sets the tcp client handler.
     *
     * @param ITCPClientHandler $handler Client handler to use for callbacks.
     *
     * @return void
     */
    public function setHandler(ITCPClientHandler $handler)
    {
        $this->_handler = $handler;
    }

    /**
     * Sets server port.
     *
     * @param integer $port Server port.
     *
     * @return void
     */
    public function setPort($port)
    {
        $this->_port = $port;
    }

    /**
     * Sets server host or ip address.
     *
     * @param string $address Server host or ip address.
     *
     * @return void
     */
    public function setAddress($address)
    {
        $this->_address = $address;
    }

    /**
     * Constructor. Not much to see here. Will register a tick function(),
     * process().
     *
     * @return void
     */
    public function __construct()
    {
        $this->_handler = false;
        $this->_socket = false;
        $this->_address = false;
        $this->_port = false;
        $this->_cTo = 0;
        $this->_rTo = 0;
        $this->_rLen = 1;
        $this->_connected = false;
        register_tick_function(array($this, 'process'));
    }
}