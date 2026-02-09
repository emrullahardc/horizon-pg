<?php

namespace HorizonPg\Listeners;

use HorizonPg\Events\SupervisorLooped;
use HorizonPg\Events\SupervisorOutOfMemory;

class MonitorSupervisorMemory
{
    /**
     * Handle the event.
     *
     * @param  \HorizonPg\Events\SupervisorLooped  $event
     * @return void
     */
    public function handle(SupervisorLooped $event)
    {
        $supervisor = $event->supervisor;

        if (($memoryUsage = $supervisor->memoryUsage()) > $supervisor->options->memory) {
            event((new SupervisorOutOfMemory($supervisor))->setMemoryUsage($memoryUsage));

            $supervisor->terminate(12);
        }
    }
}
