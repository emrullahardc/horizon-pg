<?php

namespace HorizonPg\SupervisorCommands;

use HorizonPg\Contracts\Restartable;

class Restart
{
    /**
     * Process the command.
     *
     * @param  \HorizonPg\Contracts\Restartable  $restartable
     * @return void
     */
    public function process(Restartable $restartable)
    {
        $restartable->restart();
    }
}
