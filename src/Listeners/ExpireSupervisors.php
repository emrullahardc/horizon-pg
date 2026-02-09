<?php

namespace HorizonPg\Listeners;

use HorizonPg\Contracts\MasterSupervisorRepository;
use HorizonPg\Contracts\SupervisorRepository;
use HorizonPg\Events\MasterSupervisorLooped;

class ExpireSupervisors
{
    /**
     * Handle the event.
     *
     * @param  \HorizonPg\Events\MasterSupervisorLooped  $event
     * @return void
     */
    public function handle(MasterSupervisorLooped $event)
    {
        app(MasterSupervisorRepository::class)->flushExpired();

        app(SupervisorRepository::class)->flushExpired();
    }
}
