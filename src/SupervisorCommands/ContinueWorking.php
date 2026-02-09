<?php

namespace HorizonPg\SupervisorCommands;

use HorizonPg\Contracts\Pausable;

class ContinueWorking
{
    /**
     * Process the command.
     *
     * @param  \HorizonPg\Contracts\Pausable  $pausable
     * @return void
     */
    public function process(Pausable $pausable)
    {
        $pausable->continue();
    }
}
