<?php

namespace HorizonPg\Events;

use HorizonPg\JobPayload;

class QueueEvent
{
    public $connectionName;
    public $queue;
    public $payload;

    public function __construct($payload)
    {
        $this->payload = new JobPayload($payload);
    }

    public function connection($connectionName)
    {
        $this->connectionName = $connectionName;

        return $this;
    }

    public function queue($queue)
    {
        $this->queue = $queue;

        return $this;
    }
}
