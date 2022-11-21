<?php
namespace Leafpoda\Zerorpc;

use Leafpoda\Zerorpc\Event\ZerorpcEventException;

class ZerorpcEvent {

    public $envelope;
    public $header;
    public $name;
    public $args;

    public function __construct($envelope, array $header, $name, $args = null) {

        $this->envelope = $envelope;
        $this->header = $header;
        $this->name = $name;
        $this->args = $args;
    }

    public function serialize() {
        $payload = array($this->header, $this->name);

        if (is_array($this->args)) {
            foreach($this->args as $v) {
                $payload[] = $v;
            }
        }
        
        return msgpack_pack($payload);
    }

    public static function deserialize($envelope, $payload) {
        $event = msgpack_unpack($payload);
        if (!is_array($event) || count($event) !== 3) {
            throw new ZerorpcEventException('Expected array of size 3');
        } else if (!is_array($event[0]) || !array_key_exists('message_id', $event[0])) {
            throw new ZerorpcEventException('Bad header');
        } else if (!is_string($event[1])) {
            throw new ZerorpcEventException('Bad name');
        }

        return new ZerorpcEvent($envelope, $event[0], $event[1], $event[2]);
    }
}