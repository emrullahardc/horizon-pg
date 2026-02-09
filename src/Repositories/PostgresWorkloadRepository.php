<?php

namespace HorizonPg\Repositories;

use Illuminate\Contracts\Queue\Factory as QueueFactory;
use Illuminate\Support\Str;
use HorizonPg\Contracts\MasterSupervisorRepository;
use HorizonPg\Contracts\SupervisorRepository;
use HorizonPg\Contracts\WorkloadRepository;
use HorizonPg\WaitTimeCalculator;

class PostgresWorkloadRepository implements WorkloadRepository
{
    public $queue;
    public $waitTime;
    private $masters;
    private $supervisors;

    public function __construct(
        QueueFactory $queue,
        WaitTimeCalculator $waitTime,
        MasterSupervisorRepository $masters,
        SupervisorRepository $supervisors,
    ) {
        $this->queue = $queue;
        $this->masters = $masters;
        $this->waitTime = $waitTime;
        $this->supervisors = $supervisors;
    }

    public function get()
    {
        $processes = $this->processes();

        return collect($this->waitTime->calculate())
            ->map(function ($waitTime, $queue) use ($processes) {
                [$connection, $queueName] = explode(':', $queue, 2);

                $totalProcesses = $processes[$queue] ?? 0;

                $length = ! Str::contains($queue, ',')
                    ? collect([$queueName => $this->queue->connection($connection)->readyNow($queueName)])
                    : collect(explode(',', $queueName))->mapWithKeys(function ($queueName) use ($connection) {
                        return [$queueName => $this->queue->connection($connection)->readyNow($queueName)];
                    });

                $splitQueues = Str::contains($queue, ',') ? $length->map(function ($length, $queueName) use ($connection, $totalProcesses, &$wait) {
                    return [
                        'name' => $queueName,
                        'length' => $length,
                        'wait' => $wait += $this->waitTime->calculateTimeToClear($connection, $queueName, $totalProcesses),
                    ];
                }) : null;

                return [
                    'name' => $queueName,
                    'length' => $length->sum(),
                    'wait' => $waitTime,
                    'processes' => $totalProcesses,
                    'split_queues' => $splitQueues,
                ];
            })
            ->values()
            ->toArray();
    }

    private function processes()
    {
        return collect($this->supervisors->all())
            ->pluck('processes')
            ->reduce(function ($final, $queues) {
                foreach ($queues as $queue => $processes) {
                    $final[$queue] = isset($final[$queue]) ? $final[$queue] + $processes : $processes;
                }

                return $final;
            }, []);
    }
}
