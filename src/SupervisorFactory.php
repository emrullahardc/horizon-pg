<?php

namespace HorizonPg;

class SupervisorFactory
{
    /**
     * Create a new supervisor instance.
     *
     * @param  \HorizonPg\SupervisorOptions  $options
     * @return \HorizonPg\Supervisor
     */
    public function make(SupervisorOptions $options)
    {
        return new Supervisor($options);
    }
}
