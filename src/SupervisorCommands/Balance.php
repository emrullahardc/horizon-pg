<?php

namespace HorizonPg\SupervisorCommands;

use HorizonPg\Supervisor;

class Balance
{
    /**
     * Process the command.
     *
     * @param  \HorizonPg\Supervisor  $supervisor
     * @param  array  $options
     * @return void
     */
    public function process(Supervisor $supervisor, array $options)
    {
        $supervisor->balance($options);
    }
}
