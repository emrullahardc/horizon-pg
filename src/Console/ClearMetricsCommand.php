<?php

namespace HorizonPg\Console;

use Illuminate\Console\Command;
use HorizonPg\Contracts\MetricsRepository;
use Symfony\Component\Console\Attribute\AsCommand;

#[AsCommand(name: 'horizon:clear-metrics')]
class ClearMetricsCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'horizon:clear-metrics';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Delete metrics for all jobs and queues';

    /**
     * Execute the console command.
     *
     * @param  \HorizonPg\Contracts\MetricsRepository  $metrics
     * @return void
     */
    public function handle(MetricsRepository $metrics)
    {
        $metrics->clear();

        $this->components->info('Metrics cleared successfully.');
    }
}
