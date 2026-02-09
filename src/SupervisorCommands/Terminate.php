<?php

namespace HorizonPg\SupervisorCommands;

use HorizonPg\Contracts\Terminable;

class Terminate
{
    /**
     * Process the command.
     *
     * @param  \HorizonPg\Contracts\Terminable  $terminable
     * @param  array  $options
     * @return void
     */
    public function process(Terminable $terminable, array $options)
    {
        $terminable->terminate($options['status'] ?? 0);
    }
}
