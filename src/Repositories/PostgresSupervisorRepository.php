<?php

namespace HorizonPg\Repositories;

use Carbon\CarbonImmutable;
use Illuminate\Database\ConnectionInterface;
use HorizonPg\Contracts\SupervisorRepository;
use HorizonPg\Supervisor;

class PostgresSupervisorRepository implements SupervisorRepository
{
    public $db;

    public function __construct(ConnectionInterface $db)
    {
        $this->db = $db;
    }

    public function names()
    {
        return $this->connection()->table('horizon_supervisors')
            ->where('heartbeat', '>=', CarbonImmutable::now()->subSeconds(29))
            ->orderBy('heartbeat', 'desc')
            ->pluck('name')
            ->toArray();
    }

    public function all()
    {
        return $this->get($this->names());
    }

    public function find($name)
    {
        $results = $this->get([$name]);

        return $results[0] ?? null;
    }

    public function get(array $names)
    {
        if (empty($names)) {
            return [];
        }

        return $this->connection()->table('horizon_supervisors')
            ->whereIn('name', $names)
            ->get()
            ->map(function ($record) {
                return (object) [
                    'name' => $record->name,
                    'master' => $record->master,
                    'pid' => $record->pid,
                    'status' => $record->status,
                    'processes' => json_decode($record->processes, true),
                    'options' => json_decode($record->options, true),
                ];
            })
            ->filter()
            ->values()
            ->all();
    }

    public function longestActiveTimeout()
    {
        return collect($this->all())
            ->max(fn ($supervisor) => $supervisor->options['timeout']) ?: 0;
    }

    public function update(Supervisor $supervisor)
    {
        $processes = $supervisor->processPools
            ->mapWithKeys(fn ($pool) => [$supervisor->options->connection.':'.$pool->queue() => count($pool->processes())])
            ->toJson();

        $this->connection()->table('horizon_supervisors')->updateOrInsert(
            ['name' => $supervisor->name],
            [
                'master' => implode(':', explode(':', $supervisor->name, -1)),
                'pid' => $supervisor->pid(),
                'status' => $supervisor->working ? 'running' : 'paused',
                'processes' => $processes,
                'options' => $supervisor->options->toJson(),
                'heartbeat' => CarbonImmutable::now(),
                'expires_at' => CarbonImmutable::now()->addSeconds(30),
            ]
        );
    }

    public function forget($names)
    {
        $names = (array) $names;

        if (empty($names)) {
            return;
        }

        $this->connection()->table('horizon_supervisors')
            ->whereIn('name', $names)
            ->delete();
    }

    public function flushExpired()
    {
        $this->connection()->table('horizon_supervisors')
            ->where('heartbeat', '<', CarbonImmutable::now()->subSeconds(14))
            ->delete();
    }

    protected function connection()
    {
        return $this->db;
    }
}
