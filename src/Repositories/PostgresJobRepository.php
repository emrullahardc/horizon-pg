<?php

namespace HorizonPg\Repositories;

use Carbon\CarbonImmutable;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use HorizonPg\Contracts\JobRepository;
use HorizonPg\JobPayload;

class PostgresJobRepository implements JobRepository
{
    public $db;

    public $keys = [
        'id',
        'connection',
        'queue',
        'name',
        'status',
        'payload',
        'exception',
        'context',
        'failed_at',
        'completed_at',
        'retried_by',
        'reserved_at',
    ];

    public $recentFailedJobExpires;
    public $recentJobExpires;
    public $pendingJobExpires;
    public $completedJobExpires;
    public $failedJobExpires;
    public $monitoredJobExpires;

    public function __construct(ConnectionInterface $db)
    {
        $this->db = $db;

        if ($this->connection()->getDriverName() === 'pgsql') {
            $this->db->statement('SET synchronous_commit = off');
        }
        $this->recentJobExpires = (int) config('horizon.trim.recent', 60);
        $this->pendingJobExpires = (int) config('horizon.trim.pending', 60);
        $this->completedJobExpires = (int) config('horizon.trim.completed', 60);
        $this->failedJobExpires = (int) config('horizon.trim.failed', 10080);
        $this->recentFailedJobExpires = (int) config('horizon.trim.recent_failed', $this->failedJobExpires);
        $this->monitoredJobExpires = (int) config('horizon.trim.monitored', 10080);
    }

    public function nextJobId()
    {
        if ($this->connection()->getDriverName() === 'pgsql') {
            return (string) $this->connection()->selectOne("SELECT nextval('horizon_job_id_seq') as id")->id;
        }

        return (string) \Illuminate\Support\Str::uuid();
    }

    public function totalRecent()
    {
        return $this->connection()->table('horizon_jobs')->count();
    }

    public function totalFailed()
    {
        return $this->connection()->table('horizon_jobs')
            ->where('status', 'failed')
            ->count();
    }

    public function getRecent($afterIndex = null)
    {
        return $this->getJobsByType('recent_jobs', $afterIndex);
    }

    public function getFailed($afterIndex = null)
    {
        return $this->getJobsByType('failed_jobs', $afterIndex);
    }

    public function getPending($afterIndex = null)
    {
        return $this->getJobsByType('pending_jobs', $afterIndex);
    }

    public function getCompleted($afterIndex = null)
    {
        return $this->getJobsByType('completed_jobs', $afterIndex);
    }

    public function getSilenced($afterIndex = null)
    {
        return $this->getJobsByType('silenced_jobs', $afterIndex);
    }

    public function countRecent()
    {
        return $this->countJobsByType('recent_jobs');
    }

    public function countFailed()
    {
        return $this->countJobsByType('failed_jobs');
    }

    public function countPending()
    {
        return $this->countJobsByType('pending_jobs');
    }

    public function countCompleted()
    {
        return $this->countJobsByType('completed_jobs');
    }

    public function countSilenced()
    {
        return $this->countJobsByType('silenced_jobs');
    }

    public function countRecentlyFailed()
    {
        return $this->countJobsByType('recent_failed_jobs');
    }

    protected function getJobsByType($type, $afterIndex)
    {
        $afterIndex = $afterIndex === null ? -1 : (int) $afterIndex;

        $query = $this->queryForType($type);

        $ids = $query
            ->offset($afterIndex + 1)
            ->limit(50)
            ->pluck('id')
            ->toArray();

        return $this->getJobs($ids, $afterIndex + 1);
    }

    protected function countJobsByType($type)
    {
        $minutes = $this->minutesForType($type);
        $threshold = CarbonImmutable::now()->subMinutes($minutes);

        $query = $this->connection()->table('horizon_jobs');

        return match ($type) {
            'failed_jobs' => $query->where('status', 'failed')
                ->where('failed_at', '>=', $this->microtime($threshold))
                ->count(),
            'recent_failed_jobs' => $query->where('status', 'failed')
                ->where('failed_at', '>=', $this->microtime($threshold))
                ->count(),
            'pending_jobs' => $query->where('status', 'pending')
                ->where('created_at', '>=', $this->microtime($threshold))
                ->count(),
            'completed_jobs' => $query->where('status', 'completed')
                ->where('is_silenced', false)
                ->where('completed_at', '>=', $this->microtime($threshold))
                ->count(),
            'silenced_jobs' => $query->where('status', 'completed')
                ->where('is_silenced', true)
                ->where('completed_at', '>=', $this->microtime($threshold))
                ->count(),
            default => $query->where('created_at', '>=', $this->microtime($threshold))
                ->count(),
        };
    }

    protected function queryForType($type)
    {
        $query = $this->connection()->table('horizon_jobs')->select('id');

        return match ($type) {
            'recent_jobs' => $query->orderByDesc('created_at'),
            'pending_jobs' => $query->where('status', 'pending')->orderByDesc('created_at'),
            'completed_jobs' => $query->where('status', 'completed')
                ->where('is_silenced', false)->orderByDesc('completed_at'),
            'silenced_jobs' => $query->where('status', 'completed')
                ->where('is_silenced', true)->orderByDesc('completed_at'),
            'failed_jobs' => $query->where('status', 'failed')->orderByDesc('failed_at'),
            'recent_failed_jobs' => $query->where('status', 'failed')->orderByDesc('failed_at'),
            'monitored_jobs' => $query->where('is_monitored', true)->orderByDesc('created_at'),
            default => $query->orderByDesc('created_at'),
        };
    }

    protected function minutesForType($type)
    {
        return match ($type) {
            'failed_jobs' => $this->failedJobExpires,
            'recent_failed_jobs' => $this->recentFailedJobExpires,
            'pending_jobs' => $this->pendingJobExpires,
            'completed_jobs' => $this->completedJobExpires,
            'silenced_jobs' => $this->completedJobExpires,
            default => $this->recentJobExpires,
        };
    }

    public function getJobs(array $ids, $indexFrom = 0)
    {
        if (empty($ids)) {
            return collect();
        }

        $jobs = $this->connection()->table('horizon_jobs')
            ->whereIn('id', $ids)
            ->get()
            ->keyBy('id');

        return collect($ids)
            ->map(fn($id) => $jobs->get($id))
            ->filter()
            ->values()
            ->map(function ($job) use (&$indexFrom) {
                $result = (object) [
                    'id' => $job->id,
                    'connection' => $job->connection,
                    'queue' => $job->queue,
                    'name' => $job->name,
                    'status' => $job->status,
                    'payload' => $job->payload,
                    'exception' => $job->exception,
                    'context' => $job->context,
                    'failed_at' => $job->failed_at,
                    'completed_at' => $job->completed_at,
                    'retried_by' => $job->retried_by,
                    'reserved_at' => $job->reserved_at,
                    'index' => $indexFrom,
                ];

                $indexFrom++;

                return $result;
            });
    }

    public function pushed($connection, $queue, JobPayload $payload)
    {
        $time = str_replace(',', '.', microtime(true));

        $this->connection()->table('horizon_jobs')->insert([
            'id' => $payload->id(),
            'connection' => $connection,
            'queue' => $queue,
            'name' => $payload->decoded['displayName'],
            'status' => 'pending',
            'payload' => $payload->value,
            'created_at' => $time,
            'updated_at' => $time,
            'expires_at' => CarbonImmutable::now()->addMinutes($this->pendingJobExpires),
        ]);
    }

    public function reserved($connection, $queue, JobPayload $payload)
    {
        $time = str_replace(',', '.', microtime(true));

        $this->connection()->table('horizon_jobs')
            ->where('id', $payload->id())
            ->update([
                'status' => 'reserved',
                'payload' => $payload->value,
                'updated_at' => $time,
                'reserved_at' => $time,
            ]);
    }

    public function released($connection, $queue, JobPayload $payload)
    {
        $this->connection()->table('horizon_jobs')
            ->where('id', $payload->id())
            ->update([
                'status' => 'pending',
                'payload' => $payload->value,
                'updated_at' => str_replace(',', '.', microtime(true)),
            ]);
    }

    public function remember($connection, $queue, JobPayload $payload)
    {
        $this->connection()->table('horizon_jobs')->updateOrInsert(
            ['id' => $payload->id()],
            [
                'connection' => $connection,
                'queue' => $queue,
                'name' => $payload->decoded['displayName'],
                'status' => 'completed',
                'payload' => $payload->value,
                'is_monitored' => true,
                'completed_at' => str_replace(',', '.', microtime(true)),
                'updated_at' => str_replace(',', '.', microtime(true)),
                'expires_at' => CarbonImmutable::now()->addMinutes($this->monitoredJobExpires),
            ]
        );
    }

    public function migrated($connection, $queue, Collection $payloads)
    {
        foreach ($payloads as $payload) {
            $this->connection()->table('horizon_jobs')
                ->where('id', $payload->id())
                ->update([
                    'status' => 'pending',
                    'payload' => $payload->value,
                    'updated_at' => str_replace(',', '.', microtime(true)),
                ]);
        }
    }

    public function completed(JobPayload $payload, $failed = false, $silenced = false)
    {
        if ($payload->isRetry()) {
            $this->updateRetryInformationOnParent($payload, $failed);
        }

        $this->connection()->table('horizon_jobs')
            ->where('id', $payload->id())
            ->update([
                'status' => 'completed',
                'is_silenced' => $silenced,
                'completed_at' => str_replace(',', '.', microtime(true)),
                'updated_at' => str_replace(',', '.', microtime(true)),
                'expires_at' => CarbonImmutable::now()->addMinutes($this->completedJobExpires),
            ]);
    }

    protected function updateRetryInformationOnParent(JobPayload $payload, $failed)
    {
        $retriedBy = $this->connection()->table('horizon_jobs')
            ->where('id', $payload->retryOf())
            ->value('retried_by');

        if ($retriedBy) {
            $retries = $this->updateRetryStatus(
                $payload,
                json_decode($retriedBy, true),
                $failed
            );

            $this->connection()->table('horizon_jobs')
                ->where('id', $payload->retryOf())
                ->update(['retried_by' => json_encode($retries)]);
        }
    }

    protected function updateRetryStatus(JobPayload $payload, $retries, $failed)
    {
        return collect($retries)
            ->map(function ($retry) use ($payload, $failed) {
                return $retry['id'] === $payload->id()
                    ? Arr::set($retry, 'status', $failed ? 'failed' : 'completed')
                    : $retry;
            })
            ->all();
    }

    public function deleteMonitored(array $ids)
    {
        $this->connection()->table('horizon_jobs')
            ->whereIn('id', $ids)
            ->update([
                'expires_at' => CarbonImmutable::now()->addDays(7),
            ]);
    }

    public function trimRecentJobs()
    {
        // Delete expired jobs (replaces Redis sorted set trimming + TTL)
        $this->connection()->table('horizon_jobs')
            ->whereNotNull('expires_at')
            ->where('expires_at', '<', now())
            ->delete();
    }

    public function trimFailedJobs()
    {
        $this->connection()->table('horizon_jobs')
            ->where('status', 'failed')
            ->where('failed_at', '<', $this->microtime(CarbonImmutable::now()->subMinutes($this->failedJobExpires)))
            ->delete();
    }

    public function trimMonitoredJobs()
    {
        $this->connection()->table('horizon_jobs')
            ->where('is_monitored', true)
            ->where('created_at', '<', $this->microtime(CarbonImmutable::now()->subMinutes($this->monitoredJobExpires)))
            ->delete();
    }

    public function findFailed($id)
    {
        $job = $this->connection()->table('horizon_jobs')
            ->where('id', $id)
            ->first();

        if (!$job || $job->status !== 'failed') {
            return null;
        }

        return (object) [
            'id' => $job->id,
            'connection' => $job->connection,
            'queue' => $job->queue,
            'name' => $job->name,
            'status' => $job->status,
            'payload' => $job->payload,
            'exception' => $job->exception,
            'context' => $job->context,
            'failed_at' => $job->failed_at,
            'completed_at' => $job->completed_at,
            'retried_by' => $job->retried_by,
            'reserved_at' => $job->reserved_at,
        ];
    }

    public function failed($exception, $connection, $queue, JobPayload $payload)
    {
        $time = str_replace(',', '.', microtime(true));

        $this->connection()->table('horizon_jobs')->updateOrInsert(
            ['id' => $payload->id()],
            [
                'connection' => $connection,
                'queue' => $queue,
                'name' => $payload->decoded['displayName'],
                'status' => 'failed',
                'payload' => $payload->value,
                'exception' => (string) $exception,
                'context' => method_exists($exception, 'context')
                    ? json_encode($exception->context())
                    : null,
                'failed_at' => $time,
                'updated_at' => $time,
                'is_silenced' => false,
                'expires_at' => CarbonImmutable::now()->addMinutes($this->failedJobExpires),
            ]
        );
    }

    public function storeRetryReference($id, $retryId)
    {
        $retriedBy = $this->connection()->table('horizon_jobs')
            ->where('id', $id)
            ->value('retried_by');

        $retries = json_decode($retriedBy ?: '[]', true);

        $retries[] = [
            'id' => $retryId,
            'status' => 'pending',
            'retried_at' => CarbonImmutable::now()->getTimestamp(),
        ];

        $this->connection()->table('horizon_jobs')
            ->where('id', $id)
            ->update(['retried_by' => json_encode($retries)]);
    }

    public function deleteFailed($id)
    {
        return $this->connection()->table('horizon_jobs')
            ->where('id', $id)
            ->where('status', 'failed')
            ->delete();
    }

    public function purge($queue)
    {
        return $this->connection()->table('horizon_jobs')
            ->whereIn('status', ['pending', 'reserved'])
            ->where('queue', $queue)
            ->delete();
    }

    protected function microtime(CarbonImmutable $time)
    {
        return (float) $time->getPreciseTimestamp(6) / 1000000;
    }

    protected function connection()
    {
        return $this->db;
    }
}
