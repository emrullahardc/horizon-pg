<?php

namespace HorizonPg\Repositories;

use Carbon\CarbonImmutable;
use Illuminate\Database\ConnectionInterface;
use HorizonPg\Contracts\MasterSupervisorRepository;
use HorizonPg\Contracts\SupervisorRepository;
use HorizonPg\MasterSupervisor;

class PostgresMasterSupervisorRepository implements MasterSupervisorRepository
{
    public $db;

    public function __construct(ConnectionInterface $db)
    {
        $this->db = $db;
    }

    public function names()
    {
        return $this->connection()->table('horizon_masters')
            ->where('heartbeat', '>=', CarbonImmutable::now()->subSeconds(14))
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

        return $this->connection()->table('horizon_masters')
            ->whereIn('name', $names)
            ->get()
            ->map(function ($record) {
                return (object) [
                    'name' => $record->name,
                    'environment' => $record->environment,
                    'pid' => $record->pid,
                    'status' => $record->status,
                    'supervisors' => json_decode($record->supervisors, true),
                ];
            })
            ->filter()
            ->values()
            ->all();
    }

    public function update(MasterSupervisor $master)
    {
        $supervisors = $master->supervisors->map->name->all();

        $this->connection()->table('horizon_masters')->updateOrInsert(
            ['name' => $master->name],
            [
                'environment' => $master->environment,
                'pid' => $master->pid(),
                'status' => $master->working ? 'running' : 'paused',
                'supervisors' => json_encode($supervisors),
                'heartbeat' => CarbonImmutable::now(),
                'expires_at' => CarbonImmutable::now()->addSeconds(15),
            ]
        );
    }

    public function forget($name)
    {
        if (! $master = $this->find($name)) {
            return;
        }

        app(SupervisorRepository::class)->forget(
            $master->supervisors
        );

        $this->connection()->table('horizon_masters')
            ->where('name', $name)
            ->delete();
    }

    public function flushExpired()
    {
        $this->connection()->table('horizon_masters')
            ->where('heartbeat', '<', CarbonImmutable::now()->subSeconds(14))
            ->delete();
    }

    protected function connection()
    {
        return $this->db;
    }
}
