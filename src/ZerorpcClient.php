<?php
namespace Leafpoda\Zerorpc;

use Leafpoda\Zerorpc\Client\ZerorpcClientChannel;
use Leafpoda\Zerorpc\Client\ZerorpcClientException;

class ZerorpcClient {
    const DEFAULT_TIMEOUT = 10000; // 10 seconds
    const PROTOCOL_VERSION = 3;

    private $socket;
    private $timeout;

    public function __construct($endpoint, $timeout = self::DEFAULT_TIMEOUT) {

        $this->socket = new ZerorpcSocket($endpoint, $timeout);
        $this->timeout = $timeout;
    }

    public function __call($name, $args) {
        $response = null;
        $this->invoke($name, $args, function($event) use (&$response) {
            if ($event->name === 'ERR') {
                throw new ZerorpcClientException(is_string($event->args[1]) ? $event->args[1] : null , intval($event->args[0]));
            }
        
            if ($event->name !== 'OK') {
                throw new ZerorpcClientException('Unexpected event');
            }

            $response = $event->args[0];

            // Try to be clever on what type of response we received. Both
            // array and object will be encoded as array. If the response is
            // an array, check if it's associative, and cast it to an object.
            if (is_array($response) && is_string(key($response))) {
                $response = (object) $response;
            }
        });

        $this->socket->dispatch();

        return $response;
    }

    public function invoke($name, array $args = null) {

        
        $event = new ZerorpcEvent(null, $this->createHeader(), $name, $args);
        $this->socket->sendMulti($event);
    }

    public function recv() {
        return $this->socket->recvMulti();
    }

    public function invokeAsync($name, array $args = null, $callback = null) {
        $channel = new ZerorpcClientChannel($this->socket, $this->timeout);
        if ($callback) $channel->register($callback);
        $channel->send($name, $args);
        return $channel;
    }

    public function dispatch() {
         $this->socket->dispatch();
    }


    public function createHeader() {
        return array('v'=>self::PROTOCOL_VERSION, 'message_id'=>$this->gen_uuid());
    }

    private function gen_uuid() {
        return sprintf( '%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
            // 32 bits for "time_low"
            mt_rand( 0, 0xffff ), mt_rand( 0, 0xffff ),

            // 16 bits for "time_mid"
            mt_rand( 0, 0xffff ),

            // 16 bits for "time_hi_and_version",
            // four most significant bits holds version number 4
            mt_rand( 0, 0x0fff ) | 0x4000,

            // 16 bits, 8 bits for "clk_seq_hi_res",
            // 8 bits for "clk_seq_low",
            // two most significant bits holds zero and one for variant DCE1.1
            mt_rand( 0, 0x3fff ) | 0x8000,

            // 48 bits for "node"
            mt_rand( 0, 0xffff ), mt_rand( 0, 0xffff ), mt_rand( 0, 0xffff )
        );
    }

}