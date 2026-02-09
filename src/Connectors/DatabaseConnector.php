<?php

namespace HorizonPg\Connectors;

use Illuminate\Queue\Connectors\DatabaseConnector as BaseConnector;
use Illuminate\Support\Arr;
use HorizonPg\DatabaseQueue;

class DatabaseConnector extends BaseConnector
{
    public function connect(array $config)
    {
        return new DatabaseQueue(
            $this->connections->connection(Arr::get($config, 'connection', null)),
            $config['table'],
            $config['queue'],
            Arr::get($config, 'retry_after', 60),
            Arr::get($config, 'after_commit', null),
        );
    }
}
