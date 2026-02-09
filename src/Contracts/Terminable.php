<?php

namespace HorizonPg\Contracts;

interface Terminable
{
    /**
     * Terminate the process.
     *
     * @param  int  $status
     * @return void
     */
    public function terminate($status = 0);
}
