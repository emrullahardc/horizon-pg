<?php

namespace HorizonPg\Listeners;

use HorizonPg\Contracts\MetricsRepository;
use HorizonPg\Events\JobDeleted;
use HorizonPg\Stopwatch;

class UpdateJobMetrics
{
    /**
     * The metrics repository implementation.
     *
     * @var \HorizonPg\Contracts\MetricsRepository
     */
    public $metrics;

    /**
     * The stopwatch instance.
     *
     * @var \HorizonPg\Stopwatch
     */
    public $watch;

    /**
     * Create a new listener instance.
     *
     * @param  \HorizonPg\Contracts\MetricsRepository  $metrics
     * @param  \HorizonPg\Stopwatch  $watch
     * @return void
     */
    public function __construct(MetricsRepository $metrics, Stopwatch $watch)
    {
        $this->watch = $watch;
        $this->metrics = $metrics;
    }

    /**
     * Stop gathering metrics for a job.
     *
     * @param  \HorizonPg\Events\JobDeleted  $event
     * @return void
     */
    public function handle(JobDeleted $event)
    {
        if ($event->job->hasFailed()) {
            return;
        }

        $time = $this->watch->check($id = $event->payload->id()) ?: 0;

        $this->metrics->incrementQueue(
            $event->job->getQueue(), $time
        );

        $this->metrics->incrementJob(
            $event->payload->displayName(), $time
        );

        $this->watch->forget($id);
    }
}
