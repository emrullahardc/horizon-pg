<?php

namespace HorizonPg\Listeners;

use HorizonPg\Contracts\JobRepository;
use HorizonPg\Contracts\TagRepository;
use HorizonPg\Events\JobDeleted;

class MarkJobAsComplete
{
    /**
     * The job repository implementation.
     *
     * @var \HorizonPg\Contracts\JobRepository
     */
    public $jobs;

    /**
     * The tag repository implementation.
     *
     * @var \HorizonPg\Contracts\TagRepository
     */
    public $tags;

    /**
     * Create a new listener instance.
     *
     * @param  \HorizonPg\Contracts\JobRepository  $jobs
     * @param  \HorizonPg\Contracts\TagRepository  $tags
     * @return void
     */
    public function __construct(JobRepository $jobs, TagRepository $tags)
    {
        $this->jobs = $jobs;
        $this->tags = $tags;
    }

    /**
     * Handle the event.
     *
     * @param  \HorizonPg\Events\JobDeleted  $event
     * @return void
     */
    public function handle(JobDeleted $event)
    {
        $failed = $event->job ? $event->job->hasFailed() : false;

        $this->jobs->completed($event->payload, $failed, $event->payload->isSilenced());

        if (!$failed && count($this->tags->monitored($event->payload->tags())) > 0) {
            $this->jobs->remember($event->connectionName, $event->queue, $event->payload);
        }
    }
}
