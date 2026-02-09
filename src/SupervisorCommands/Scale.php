<?php

namespace HorizonPg\SupervisorCommands;

use HorizonPg\Supervisor;

class Scale
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
        $supervisor->scale($options['scale']);
    }
}
