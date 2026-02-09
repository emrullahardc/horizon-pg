<?php

namespace HorizonPg\Listeners;

use HorizonPg\Events\JobReserved;
use HorizonPg\Stopwatch;

class StartTimingJob
{
    /**
     * The stopwatch instance.
     *
     * @var \HorizonPg\Stopwatch
     */
    public $watch;

    /**
     * Create a new listener instance.
     *
     * @param  \HorizonPg\Stopwatch  $watch
     * @return void
     */
    public function __construct(Stopwatch $watch)
    {
        $this->watch = $watch;
    }

    /**
     * Handle the event.
     *
     * @param  \HorizonPg\Events\JobReserved  $event
     * @return void
     */
    public function handle(JobReserved $event)
    {
        $this->watch->start($event->payload->id());
    }
}
