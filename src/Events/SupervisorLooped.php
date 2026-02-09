<?php

namespace HorizonPg\Events;

use HorizonPg\Supervisor;

class SupervisorLooped
{
    /**
     * The supervisor instance.
     *
     * @var \HorizonPg\Supervisor
     */
    public $supervisor;

    /**
     * Create a new event instance.
     *
     * @param  \HorizonPg\Supervisor  $supervisor
     * @return void
     */
    public function __construct(Supervisor $supervisor)
    {
        $this->supervisor = $supervisor;
    }
}
