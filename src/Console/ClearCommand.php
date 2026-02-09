<?php

namespace HorizonPg\Console;

use Illuminate\Console\Command;
use Illuminate\Console\ConfirmableTrait;
use Illuminate\Queue\QueueManager;
use Illuminate\Support\Arr;
use HorizonPg\Contracts\JobRepository;
use Symfony\Component\Console\Attribute\AsCommand;

#[AsCommand(name: 'horizon:clear')]
class ClearCommand extends Command
{
    use ConfirmableTrait;

    protected $signature = 'horizon:clear
                            {connection? : The name of the queue connection}
                            {--queue= : The name of the queue to clear}
                            {--force : Force the operation to run when in production}';

    protected $description = 'Delete all of the jobs from the specified queue';

    public function handle(JobRepository $jobRepository, QueueManager $manager)
    {
        if (! $this->confirmToProceed()) {
            return 1;
        }

        $connection = $this->argument('connection')
            ?: Arr::first($this->laravel['config']->get('horizon.defaults'))['connection'] ?? 'database';

        $queue = $this->getQueue($connection);

        if (method_exists($jobRepository, 'purge')) {
            $jobRepository->purge($queue);
        }

        $queueConnection = $manager->connection($connection);

        if (method_exists($queueConnection, 'clear')) {
            $count = $queueConnection->clear($queue);
            $this->components->info('Cleared '.$count.' jobs from the ['.$queue.'] queue.');
        } else {
            $this->components->info('Queue cleared via Horizon job repository.');
        }

        return 0;
    }

    protected function getQueue($connection)
    {
        return $this->option('queue') ?: $this->laravel['config']->get(
            "queue.connections.{$connection}.queue",
            'default'
        );
    }
}
