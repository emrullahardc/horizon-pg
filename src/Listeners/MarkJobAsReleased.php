<?php

namespace HorizonPg\Listeners;

use HorizonPg\Contracts\JobRepository;
use HorizonPg\Events\JobReleased;

class MarkJobAsReleased
{
    /**
     * The job repository implementation.
     *
     * @var \HorizonPg\Contracts\JobRepository
     */
    public $jobs;

    /**
     * Create a new listener instance.
     *
     * @param  \HorizonPg\Contracts\JobRepository  $jobs
     * @return void
     */
    public function __construct(JobRepository $jobs)
    {
        $this->jobs = $jobs;
    }

    /**
     * Handle the event.
     *
     * @param  \HorizonPg\Events\JobReleased  $event
     * @return void
     */
    public function handle(JobReleased $event)
    {
        $this->jobs->released($event->connectionName, $event->queue, $event->payload);
    }
}
