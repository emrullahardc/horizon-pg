<?php

namespace HorizonPg\Events;

use HorizonPg\SupervisorProcess;

class SupervisorProcessRestarting
{
    /**
     * The supervisor process instance.
     *
     * @var \HorizonPg\SupervisorProcess
     */
    public $process;

    /**
     * Create a new event instance.
     *
     * @param  \HorizonPg\SupervisorProcess  $process
     * @return void
     */
    public function __construct(SupervisorProcess $process)
    {
        $this->process = $process;
    }
}
