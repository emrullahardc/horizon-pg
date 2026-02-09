<?php

namespace HorizonPg\Listeners;

use Carbon\CarbonImmutable;
use HorizonPg\Contracts\JobRepository;
use HorizonPg\Events\MasterSupervisorLooped;

class TrimMonitoredJobs
{
    /**
     * The last time the monitored jobs were trimmed.
     *
     * @var \Carbon\CarbonImmutable
     */
    public $lastTrimmed;

    /**
     * How many minutes to wait in between each trim.
     *
     * @var int
     */
    public $frequency = 1440;

    /**
     * Handle the event.
     *
     * @param  \HorizonPg\Events\MasterSupervisorLooped  $event
     * @return void
     */
    public function handle(MasterSupervisorLooped $event)
    {
        if (! isset($this->lastTrimmed)) {
            $this->frequency = max(1, intdiv(
                config('horizon.trim.monitored', 10080), 12
            ));

            $this->lastTrimmed = CarbonImmutable::now()->subMinutes($this->frequency + 1);
        }

        if ($this->lastTrimmed->lte(CarbonImmutable::now()->subMinutes($this->frequency))) {
            app(JobRepository::class)->trimMonitoredJobs();

            $this->lastTrimmed = CarbonImmutable::now();
        }
    }
}
