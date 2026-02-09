<?php

namespace HorizonPg\Listeners;

use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Queue\Events\JobFailed as LaravelJobFailed;
use HorizonPg\Events\JobFailed;

class MarshalFailedEvent
{
    public $events;

    public function __construct(Dispatcher $events)
    {
        $this->events = $events;
    }

    public function handle(LaravelJobFailed $event)
    {
        if (! method_exists($event->job, 'getRawBody')) {
            return;
        }

        $this->events->dispatch((new JobFailed(
            $event->exception, $event->job, $event->job->getRawBody()
        ))->connection($event->connectionName)->queue($event->job->getQueue()));
    }
}
