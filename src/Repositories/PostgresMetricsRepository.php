<?php

namespace HorizonPg\Repositories;

use Carbon\CarbonImmutable;
use Illuminate\Database\ConnectionInterface;
use HorizonPg\Contracts\MetricsRepository;
use HorizonPg\Lock;
use HorizonPg\WaitTimeCalculator;

class PostgresMetricsRepository implements MetricsRepository
{
    public $db;

    public function __construct(ConnectionInterface $db)
    {
        $this->db = $db;
    }

    public function measuredJobs()
    {
        return $this->connection()->table('horizon_metrics')
            ->where('type', 'job')
            ->pluck('key')
            ->map(fn ($key) => preg_match('/^job:(.*)$/', $key, $m) ? $m[1] : $key)
            ->sort()
            ->values()
            ->all();
    }

    public function measuredQueues()
    {
        return $this->connection()->table('horizon_metrics')
            ->where('type', 'queue')
            ->pluck('key')
            ->map(fn ($key) => preg_match('/^queue:(.*)$/', $key, $m) ? $m[1] : $key)
            ->sort()
            ->values()
            ->all();
    }

    public function jobsProcessedPerMinute()
    {
        return round($this->throughput() / $this->minutesSinceLastSnapshot());
    }

    public function throughput()
    {
        return (int) $this->connection()->table('horizon_metrics')
            ->where('type', 'queue')
            ->sum('throughput');
    }

    public function throughputForJob($job)
    {
        return $this->throughputFor('job:'.$job);
    }

    public function throughputForQueue($queue)
    {
        return $this->throughputFor('queue:'.$queue);
    }

    protected function throughputFor($key)
    {
        return (int) ($this->connection()->table('horizon_metrics')
            ->where('key', $key)
            ->value('throughput') ?? 0);
    }

    public function runtimeForJob($job)
    {
        return $this->runtimeFor('job:'.$job);
    }

    public function runtimeForQueue($queue)
    {
        return $this->runtimeFor('queue:'.$queue);
    }

    protected function runtimeFor($key)
    {
        return (float) ($this->connection()->table('horizon_metrics')
            ->where('key', $key)
            ->value('runtime') ?? 0);
    }

    public function queueWithMaximumRuntime()
    {
        return collect($this->measuredQueues())
            ->sortBy(function ($queue) {
                $snapshot = $this->connection()->table('horizon_metric_snapshots')
                    ->where('key', 'queue:'.$queue)
                    ->orderBy('time', 'desc')
                    ->first();

                return $snapshot->runtime ?? 0;
            })
            ->last();
    }

    public function queueWithMaximumThroughput()
    {
        return collect($this->measuredQueues())
            ->sortBy(function ($queue) {
                $snapshot = $this->connection()->table('horizon_metric_snapshots')
                    ->where('key', 'queue:'.$queue)
                    ->orderBy('time', 'desc')
                    ->first();

                return $snapshot->throughput ?? 0;
            })
            ->last();
    }

    public function incrementJob($job, $runtime)
    {
        $key = 'job:'.$job;
        $runtime = (float) str_replace(',', '.', (string) $runtime);

        $this->connection()->statement("
            INSERT INTO horizon_metrics (key, type, throughput, runtime, updated_at)
            VALUES (?, 'job', 1, ?, NOW())
            ON CONFLICT (key) DO UPDATE SET
                throughput = horizon_metrics.throughput + 1,
                runtime = CASE
                    WHEN horizon_metrics.throughput = 0 THEN EXCLUDED.runtime
                    ELSE ((horizon_metrics.throughput * horizon_metrics.runtime) + EXCLUDED.runtime) / (horizon_metrics.throughput + 1)
                END,
                updated_at = NOW()
        ", [$key, $runtime]);
    }

    public function incrementQueue($queue, $runtime)
    {
        $key = 'queue:'.$queue;
        $runtime = (float) str_replace(',', '.', (string) $runtime);

        $this->connection()->statement("
            INSERT INTO horizon_metrics (key, type, throughput, runtime, updated_at)
            VALUES (?, 'queue', 1, ?, NOW())
            ON CONFLICT (key) DO UPDATE SET
                throughput = horizon_metrics.throughput + 1,
                runtime = CASE
                    WHEN horizon_metrics.throughput = 0 THEN EXCLUDED.runtime
                    ELSE ((horizon_metrics.throughput * horizon_metrics.runtime) + EXCLUDED.runtime) / (horizon_metrics.throughput + 1)
                END,
                updated_at = NOW()
        ", [$key, $runtime]);
    }

    public function snapshotsForJob($job)
    {
        return $this->snapshotsFor('job:'.$job);
    }

    public function snapshotsForQueue($queue)
    {
        return $this->snapshotsFor('queue:'.$queue);
    }

    protected function snapshotsFor($key)
    {
        return $this->connection()->table('horizon_metric_snapshots')
            ->where('key', $key)
            ->orderBy('time')
            ->get()
            ->map(function ($snapshot) {
                $result = [
                    'throughput' => (int) $snapshot->throughput,
                    'runtime' => (float) $snapshot->runtime,
                    'time' => $snapshot->time,
                ];

                if ($snapshot->wait !== null) {
                    $result['wait'] = (float) $snapshot->wait;
                }

                return (object) $result;
            })
            ->values()
            ->all();
    }

    public function snapshot()
    {
        collect($this->measuredJobs())->each(function ($job) {
            $this->storeSnapshotForJob($job);
        });

        collect($this->measuredQueues())->each(function ($queue) {
            $this->storeSnapshotForQueue($queue);
        });

        $this->storeSnapshotTimestamp();
    }

    protected function storeSnapshotForJob($job)
    {
        $key = 'job:'.$job;
        $data = $this->baseSnapshotData($key);
        $time = CarbonImmutable::now()->getTimestamp();

        $this->connection()->table('horizon_metric_snapshots')->insert([
            'key' => $key,
            'throughput' => $data['throughput'],
            'runtime' => $data['runtime'],
            'time' => $time,
            'created_at' => now(),
        ]);

        $this->trimSnapshots($key, config('horizon.metrics.trim_snapshots.job', 24));
    }

    protected function storeSnapshotForQueue($queue)
    {
        $key = 'queue:'.$queue;
        $data = $this->baseSnapshotData($key);
        $time = CarbonImmutable::now()->getTimestamp();

        $this->connection()->table('horizon_metric_snapshots')->insert([
            'key' => $key,
            'throughput' => $data['throughput'],
            'runtime' => $data['runtime'],
            'wait' => app(WaitTimeCalculator::class)->calculateFor($queue),
            'time' => $time,
            'created_at' => now(),
        ]);

        $this->trimSnapshots($key, config('horizon.metrics.trim_snapshots.queue', 24));
    }

    protected function trimSnapshots($key, $keep)
    {
        $ids = $this->connection()->table('horizon_metric_snapshots')
            ->where('key', $key)
            ->orderBy('time', 'desc')
            ->skip($keep)
            ->limit(1000)
            ->pluck('id');

        if ($ids->isNotEmpty()) {
            $this->connection()->table('horizon_metric_snapshots')
                ->whereIn('id', $ids)
                ->delete();
        }
    }

    protected function baseSnapshotData($key)
    {
        return $this->connection()->transaction(function () use ($key) {
            $row = $this->connection()->table('horizon_metrics')
                ->where('key', $key)
                ->lockForUpdate()
                ->first();

            if ($row) {
                $this->connection()->table('horizon_metrics')
                    ->where('key', $key)
                    ->update(['throughput' => 0, 'runtime' => 0, 'updated_at' => now()]);
            }

            return [
                'throughput' => $row->throughput ?? 0,
                'runtime' => $row->runtime ?? 0,
            ];
        });
    }

    protected function minutesSinceLastSnapshot()
    {
        $lastSnapshotAt = $this->connection()->table('horizon_meta')
            ->where('key', 'last_snapshot_at')
            ->value('value');

        if (! $lastSnapshotAt) {
            $lastSnapshotAt = $this->storeSnapshotTimestamp();
        }

        return max(
            (CarbonImmutable::now()->getTimestamp() - (int) $lastSnapshotAt) / 60, 1
        );
    }

    protected function storeSnapshotTimestamp()
    {
        $timestamp = CarbonImmutable::now()->getTimestamp();

        $this->connection()->table('horizon_meta')->updateOrInsert(
            ['key' => 'last_snapshot_at'],
            ['value' => (string) $timestamp]
        );

        return $timestamp;
    }

    public function acquireWaitTimeMonitorLock()
    {
        return app(Lock::class)->get('monitor:time-to-clear');
    }

    public function forget($key)
    {
        $this->connection()->table('horizon_metrics')
            ->where('key', $key)
            ->delete();

        $this->connection()->table('horizon_metric_snapshots')
            ->where('key', $key)
            ->delete();
    }

    public function clear()
    {
        $this->connection()->table('horizon_meta')
            ->where('key', 'last_snapshot_at')
            ->delete();

        $this->connection()->table('horizon_metrics')->truncate();
        $this->connection()->table('horizon_metric_snapshots')->truncate();
    }

    public function connection()
    {
        return $this->db;
    }
}
