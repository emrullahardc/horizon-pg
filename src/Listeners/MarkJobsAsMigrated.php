<?php

namespace HorizonPg\Listeners;

use HorizonPg\Contracts\JobRepository;
use HorizonPg\Events\JobsMigrated;

class MarkJobsAsMigrated
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
     * @param  \HorizonPg\Events\JobsMigrated  $event
     * @return void
     */
    public function handle(JobsMigrated $event)
    {
        $this->jobs->migrated($event->connectionName, $event->queue, $event->payloads);
    }
}
