<?php

namespace HorizonPg;

use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Queue\DatabaseQueue as BaseQueue;
use Illuminate\Support\Str;
use HorizonPg\Events\JobDeleted;
use HorizonPg\Events\JobPending;
use HorizonPg\Events\JobPushed;
use HorizonPg\Events\JobReleased;
use HorizonPg\Events\JobReserved;
use HorizonPg\Events\JobsMigrated;

class DatabaseQueue extends BaseQueue
{
    protected $lastPushed;

    public function readyNow($queue = null)
    {
        return $this->database->table($this->table)
            ->where('queue', $this->getQueue($queue))
            ->whereNull('reserved_at')
            ->where('available_at', '<=', $this->currentTime())
            ->count();
    }

    #[\Override]
    public function push($job, $data = '', $queue = null)
    {
        $this->lastPushed = $job;

        return $this->enqueueUsing(
            $job,
            $this->createPayload($job, $this->getQueue($queue), $data),
            $queue,
            null,
            function ($payload, $queue) {
                return $this->pushToDatabase($queue, $payload);
            }
        );
    }

    #[\Override]
    public function pushRaw($payload, $queue = null, array $options = [])
    {
        return $this->pushToDatabase($queue, $payload, $options['delay'] ?? 0, $options['attempts'] ?? 0);
    }

    #[\Override]
    protected function createPayloadArray($job, $queue, $data = '')
    {
        $payload = parent::createPayloadArray($job, $queue, $data);

        $payload['id'] = $payload['uuid'];

        return $payload;
    }

    #[\Override]
    public function later($delay, $job, $data = '', $queue = null)
    {
        $this->lastPushed = $job;

        return $this->enqueueUsing(
            $job,
            $this->createPayload($job, $this->getQueue($queue), $data),
            $queue,
            $delay,
            function ($payload, $queue, $delay) {
                return $this->pushToDatabase($queue, $payload, $delay);
            }
        );
    }

    #[\Override]
    public function pop($queue = null)
    {
        return tap(parent::pop($queue), function ($result) use ($queue) {
            if ($result) {
                $this->event($this->getQueue($queue), new JobReserved($result->getRawBody()));
            }
        });
    }

    #[\Override]
    public function deleteReserved($queue, $id)
    {
        $rawBody = null;

        if (is_object($id) && method_exists($id, 'getRawBody')) {
            $rawBody = $id->getRawBody();
        } else {
            $rawBody = $this->database->table($this->table)->where('id', $id)->value('payload');
        }

        parent::deleteReserved($queue, $id);

        if ($rawBody) {
            $this->event($this->getQueue($queue), new JobDeleted(null, $rawBody));
        }
    }

    #[\Override]
    public function deleteAndRelease($queue, $job, $delay)
    {
        parent::deleteAndRelease($queue, $job, $delay);

        if (method_exists($job, 'getRawBody')) {
            $this->event($this->getQueue($queue), new JobReleased($job->getRawBody()));
        }
    }

    protected function pushToDatabase($queue, $payload, $delay = 0, $attempts = 0)
    {
        $jobPayload = (new JobPayload($payload))->prepare($this->lastPushed);

        $this->event($this->getQueue($queue), new JobPending($jobPayload->value));

        parent::pushToDatabase($queue, $jobPayload->value, $delay, $attempts);

        $this->event($this->getQueue($queue), new JobPushed($jobPayload->value));

        return $jobPayload->id();
    }

    protected function event($queue, $event)
    {
        if ($this->container && $this->container->bound(Dispatcher::class)) {
            $this->container->make(Dispatcher::class)->dispatch(
                $event->connection($this->getConnectionName())->queue($queue)
            );
        }
    }
}
