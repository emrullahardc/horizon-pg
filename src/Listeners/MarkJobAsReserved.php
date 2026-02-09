<?php

namespace HorizonPg\Listeners;

use HorizonPg\Contracts\JobRepository;
use HorizonPg\Events\JobReserved;

class MarkJobAsReserved
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
     * @param  \HorizonPg\Events\JobReserved  $event
     * @return void
     */
    public function handle(JobReserved $event)
    {
        $this->jobs->reserved($event->connectionName, $event->queue, $event->payload);
    }
}
