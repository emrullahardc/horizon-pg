<?php

namespace HorizonPg\Repositories;

use Illuminate\Database\ConnectionInterface;
use HorizonPg\Contracts\TagRepository;

class PostgresTagRepository implements TagRepository
{
    public $db;

    public function __construct(ConnectionInterface $db)
    {
        $this->db = $db;
    }

    public function monitoring()
    {
        return $this->connection()->table('horizon_monitoring')
            ->pluck('tag')
            ->toArray();
    }

    public function monitored(array $tags)
    {
        return array_values(array_intersect($tags, $this->monitoring()));
    }

    public function monitor($tag)
    {
        $this->connection()->table('horizon_monitoring')->insertOrIgnore([
            'tag' => $tag,
        ]);
    }

    public function stopMonitoring($tag)
    {
        $this->connection()->table('horizon_monitoring')
            ->where('tag', $tag)
            ->delete();
    }

    public function add($id, array $tags)
    {
        $score = str_replace(',', '.', microtime(true));

        $rows = [];
        foreach ($tags as $tag) {
            $rows[] = [
                'job_id' => $id,
                'tag' => $tag,
                'score' => $score,
            ];
        }

        if (! empty($rows)) {
            $this->connection()->table('horizon_tags')->upsert(
                $rows,
                ['tag', 'job_id'],
                ['score']
            );
        }
    }

    public function addTemporary($minutes, $id, array $tags)
    {
        $score = str_replace(',', '.', microtime(true));
        $expiresAt = now()->addMinutes($minutes);

        $rows = [];
        foreach ($tags as $tag) {
            $rows[] = [
                'job_id' => $id,
                'tag' => $tag,
                'score' => $score,
                'expires_at' => $expiresAt,
            ];
        }

        if (! empty($rows)) {
            $this->connection()->table('horizon_tags')->upsert(
                $rows,
                ['tag', 'job_id'],
                ['score', 'expires_at']
            );
        }
    }

    public function count($tag)
    {
        return $this->connection()->table('horizon_tags')
            ->where('tag', $tag)
            ->count();
    }

    public function jobs($tag)
    {
        return $this->connection()->table('horizon_tags')
            ->where('tag', $tag)
            ->orderBy('score')
            ->pluck('job_id')
            ->toArray();
    }

    public function paginate($tag, $startingAt = 0, $limit = 25)
    {
        $tags = $this->connection()->table('horizon_tags')
            ->where('tag', $tag)
            ->orderBy('score', 'desc')
            ->offset($startingAt)
            ->limit($limit)
            ->pluck('job_id')
            ->values();

        return $tags
            ->mapWithKeys(fn ($jobId, $index) => [$index + $startingAt => $jobId])
            ->all();
    }

    public function forgetJobs($tags, $ids)
    {
        $this->connection()->table('horizon_tags')
            ->whereIn('tag', (array) $tags)
            ->whereIn('job_id', (array) $ids)
            ->delete();
    }

    public function forget($tag)
    {
        $this->connection()->table('horizon_tags')
            ->where('tag', $tag)
            ->delete();
    }

    protected function connection()
    {
        return $this->db;
    }
}
