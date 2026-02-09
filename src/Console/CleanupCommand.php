<?php

namespace HorizonPg\Console;

use Illuminate\Console\Command;
use Illuminate\Database\ConnectionInterface;

class CleanupCommand extends Command
{
    protected $signature = 'horizon:cleanup';

    protected $description = 'Clean up expired Horizon records from PostgreSQL';

    public function handle(ConnectionInterface $db)
    {
        $jobsDeleted = $db->table('horizon_jobs')
            ->whereNotNull('expires_at')
            ->where('expires_at', '<', now())
            ->delete();

        $tagsDeleted = $db->table('horizon_tags')
            ->whereNotNull('expires_at')
            ->where('expires_at', '<', now())
            ->delete();

        $db->table('horizon_masters')
            ->where('expires_at', '<', now())
            ->delete();

        $db->table('horizon_supervisors')
            ->where('expires_at', '<', now())
            ->delete();

        $this->info("Cleaned up {$jobsDeleted} expired jobs and {$tagsDeleted} expired tags.");
    }
}
