<?php

namespace Leafpoda\Zerorpc\Client;

use Leafpoda\Zerorpc\ZerorpcChannel;

class ZerorpcClientChannel extends ZerorpcChannel
{

    private $fresh;

    public function __construct($socket, $timeout)
    {
        parent::__construct(uniqid(), null, $socket, $timeout);
        $this->fresh = true;
    }

    public function createHeader()
    {
        if ($this->fresh) {
            $this->fresh = false;
            return array('v' => parent::PROTOCOL_VERSION, 'message_id' => $this->id);
        } else {
            return parent::createHeader();
        }
    }
}