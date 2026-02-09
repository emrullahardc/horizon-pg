<?php

namespace HorizonPg\Repositories;

use Carbon\CarbonImmutable;
use Illuminate\Database\ConnectionInterface;
use HorizonPg\Contracts\ProcessRepository;

class PostgresProcessRepository implements ProcessRepository
{
    public $db;

    public function __construct(ConnectionInterface $db)
    {
        $this->db = $db;
    }

    public function allOrphans($master)
    {
        return $this->connection()->table('horizon_orphans')
            ->where('master', $master)
            ->pluck('observed_at', 'process_id')
            ->toArray();
    }

    public function orphaned($master, array $processIds)
    {
        $time = CarbonImmutable::now()->getTimestamp();

        // Remove processes no longer orphaned
        $this->connection()->table('horizon_orphans')
            ->where('master', $master)
            ->whereNotIn('process_id', $processIds)
            ->delete();

        // Insert new orphaned processes (ignore if already exists)
        foreach ($processIds as $processId) {
            $this->connection()->table('horizon_orphans')->insertOrIgnore([
                'master' => $master,
                'process_id' => $processId,
                'observed_at' => $time,
            ]);
        }
    }

    public function orphanedFor($master, $seconds)
    {
        $expiresAt = CarbonImmutable::now()->getTimestamp() - $seconds;

        return collect($this->allOrphans($master))
            ->filter(fn ($recordedAt, $_) => $expiresAt > $recordedAt)
            ->keys()
            ->all();
    }

    public function forgetOrphans($master, array $processIds)
    {
        $this->connection()->table('horizon_orphans')
            ->where('master', $master)
            ->whereIn('process_id', $processIds)
            ->delete();
    }

    protected function connection()
    {
        return $this->db;
    }
}
