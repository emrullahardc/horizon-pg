<?php

namespace HorizonPg\Listeners;

use HorizonPg\Stopwatch;

class ForgetJobTimer
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
     * @param  \Illuminate\Queue\Events\JobExceptionOccurred|\Illuminate\Queue\Events\JobFailed  $event
     * @return void
     */
    public function handle($event)
    {
        $this->watch->forget($event->job->getJobId());
    }
}
