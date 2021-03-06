<?php
/**
 * TCP client
 * @author zengfanwei
 */
namespace frame\client;


class Tcp extends Base
{

    public $ip;
    public $port;
    public $data;
    public $key;
    public $timeout = 5;
    public $calltime;

    public function __construct($ip, $port, $data, $timeout)
    {
        $this->ip = $ip;
        $this->port = $port;
        $this->data = $data;
        $this->timeout = $timeout;
        $this->key = md5($ip . $port . microtime(true) . rand(0, 10000));
    }

    public function setKey($key)
    {
        $this->key = $key;
    }

    public function getKey()
    {
        return $this->key;
    }

    public function send(callable $callback)
    {

        $client = new  \swoole_client(SWOOLE_SOCK_TCP, SWOOLE_SOCK_ASYNC);

        $client->on("connect", function ($cli) {
            $cli->send($this->data);
        });

        $client->on('close', function ($cli) {
        });

        $client->on('error', function ($cli) use ($callback) {

            $cli->close();
            $this->calltime = microtime(true) - $this->calltime;
            call_user_func_array($callback, array('r' => 1, 'key' => $this->key, 'calltime' => $this->calltime, 'error_msg' => 'conncet error'));
        });

        $client->on("receive", function ($cli, $data) use ($callback) {

            //临时方案
            Timer::del($this->key);           
            $cli->close();
            $this->calltime = microtime(true) - $this->calltime;
            call_user_func_array($callback, array('r' => 0, 'key' => $this->key, 'calltime' => $this->calltime, 'data' => $data));
        });

        if ($client->connect($this->ip, $this->port, $this->timeout, 1)) {
            $this->calltime = microtime(true);
            if (floatval(($this->timeout)) > 0) {
                Timer::add($this->key, $this->timeout, $client, $callback, array('r' => 2, 'key' => $this->key, 'calltime' => $this->calltime, 'error_msg' => $this->ip . ':' . $this->port . ' timeout'));
                /*
                $this ->timer = swoole_timer_after(floatval($this ->timeout) * 1000, function() use($client,$callback){
                    $client ->close();
                    \frame\log\Log::error(__METHOD__." TIMEOUT ", __CLASS__);
                    $this ->calltime = microtime(true) - $this ->calltime;
                    call_user_func_array($callback, array('r' => 2 ,'key' => '', 'calltime' => $this ->calltime, 'error_msg' => 'timeout'));
                });
                */
            }
        }
    }
}