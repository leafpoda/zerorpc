<?php
namespace Leafpoda\Zerorpc;

use Leafpoda\Zerorpc\Socket\ZerorpcSocketException;
use ZMQ;
use ZMQContext;
use ZMQSocket;

class ZerorpcSocket {
    private $zmq;
    private $timeout;

    public function __construct($endpoint, $timeout) {
        
        $this->zmq = new ZMQSocket(new ZMQContext(), ZMQ::SOCKET_REQ);
        $this->zmq->setSockOpt(ZMQ::SOCKOPT_RCVTIMEO, $timeout);
        $this->zmq->setSockOpt(ZMQ::SOCKOPT_LINGER, 0);
        $this->zmq->connect($endpoint);
        $this->timeout = $timeout;
    }

    public function recvMulti() {
        if (!($recv = $this->zmq->recvMulti())) {
            throw new ZerorpcSocketException('Lost remote after '.$this->timeout.'ms');
        }

        if (strlen($recv[count($recv)-2]) !== 0) {
            throw new ZerorpcSocketException('Expected second to last argument to be an empty buffer, but it is not');
        }

        $envelope = array_slice($recv, 0, count($recv)-2);
        return ZerorpcEvent::deserialize($envelope, $recv[count($recv)-1]);
    }

    public function sendMulti(ZerorpcEvent $event) {

        $message = ($event->envelope) ?: array(null);
        $message[] = $event->serialize();
        $this->zmq->sendMulti($message);
    }

    public function dispatch() {
        do {
            if (!($recv = $this->zmq->recvMulti())) {
                throw new ZerorpcSocketException('Lost remote after '.$this->timeout.'ms');
            }

            if (strlen($recv[count($recv)-2]) !== 0) {
                throw new ZerorpcSocketException('Expected second to last argument to be an empty buffer, but it is not');
            }

            $envelope = array_slice($recv, 0, count($recv)-2);
            $event = ZerorpcEvent::deserialize($envelope, $recv[count($recv)-1]);

            $channel = ZerorpcChannel::get($event->header['response_to']);
            if ($channel) $channel->invoke($event);
        } while (ZerorpcChannel::count() > 0);
    }
}