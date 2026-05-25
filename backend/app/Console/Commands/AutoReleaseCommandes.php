<?php

namespace App\Console\Commands;

use App\Services\OrderWorkflowService;
use Illuminate\Console\Command;

class AutoReleaseCommandes extends Command
{
    protected $signature = 'commandes:auto-release';

    protected $description = 'Valide automatiquement les commandes livrées après la période de révision';

    public function handle(OrderWorkflowService $workflow): int
    {
        $workflow->sendReviewReminders();
        $n = $workflow->autoRelease();
        $this->info("Commandes auto-validées : {$n}");

        return self::SUCCESS;
    }
}
