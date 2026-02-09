<?php

namespace HorizonPg\Events;

use HorizonPg\WorkerProcess;

class UnableToLaunchProcess
{
    /**
     * The worker process instance.
     *
     * @var \HorizonPg\WorkerProcess
     */
    public $process;

    /**
     * Create a new event instance.
     *
     * @param  \HorizonPg\WorkerProcess  $process
     * @return void
     */
    public function __construct(WorkerProcess $process)
    {
        $this->process = $process;
    }
}
