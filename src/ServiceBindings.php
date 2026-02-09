<?php

namespace HorizonPg;

trait ServiceBindings
{
    /**
     * All of the service bindings for Horizon.
     *
     * @var array
     */
    public $serviceBindings = [
        // General services...
        AutoScaler::class,
        Contracts\HorizonCommandQueue::class => PostgresHorizonCommandQueue::class,
        Listeners\TrimRecentJobs::class,
        Listeners\TrimFailedJobs::class,
        Listeners\TrimMonitoredJobs::class,
        Lock::class,
        Stopwatch::class,

        // Repository services...
        Contracts\JobRepository::class => Repositories\PostgresJobRepository::class,
        Contracts\MasterSupervisorRepository::class => Repositories\PostgresMasterSupervisorRepository::class,
        Contracts\MetricsRepository::class => Repositories\PostgresMetricsRepository::class,
        Contracts\ProcessRepository::class => Repositories\PostgresProcessRepository::class,
        Contracts\SupervisorRepository::class => Repositories\PostgresSupervisorRepository::class,
        Contracts\TagRepository::class => Repositories\PostgresTagRepository::class,
        Contracts\WorkloadRepository::class => Repositories\PostgresWorkloadRepository::class,

        // Notifications...
        Contracts\LongWaitDetectedNotification::class => Notifications\LongWaitDetected::class,
    ];
}
