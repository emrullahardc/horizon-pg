<?php

namespace HorizonPg\Events;

use HorizonPg\MasterSupervisor;

class MasterSupervisorOutOfMemory
{
    /**
     * The master supervisor instance.
     *
     * @var \HorizonPg\MasterSupervisor
     */
    public $master;

    /**
     * Create a new event instance.
     *
     * @param  \HorizonPg\MasterSupervisor  $master
     * @return void
     */
    public function __construct(MasterSupervisor $master)
    {
        $this->master = $master;
    }
}
