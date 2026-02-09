<?php

namespace HorizonPg;

use Illuminate\Database\ConnectionInterface;
use HorizonPg\Contracts\HorizonCommandQueue;

class PostgresHorizonCommandQueue implements HorizonCommandQueue
{
    public $db;

    public function __construct(ConnectionInterface $db)
    {
        $this->db = $db;
    }

    public function push($name, $command, array $options = [])
    {
        $this->connection()->table('horizon_commands')->insert([
            'name' => $name,
            'command' => $command,
            'options' => json_encode($options),
            'created_at' => now(),
        ]);
    }

    public function pending($name)
    {
        return $this->connection()->transaction(function () use ($name) {
            $commands = $this->connection()->table('horizon_commands')
                ->where('name', $name)
                ->orderBy('id')
                ->lockForUpdate()
                ->get();

            if ($commands->isEmpty()) {
                return [];
            }

            $this->connection()->table('horizon_commands')
                ->whereIn('id', $commands->pluck('id'))
                ->delete();

            return $commands->map(function ($row) {
                return (object) [
                    'command' => $row->command,
                    'options' => json_decode($row->options, true),
                ];
            })->all();
        });
    }

    public function flush($name)
    {
        $this->connection()->table('horizon_commands')
            ->where('name', $name)
            ->delete();
    }

    protected function connection()
    {
        return $this->db;
    }
}
