<?php

namespace App\Console\Commands;

use App\Services\OrderWorkflowService;
use Illuminate\Console\Command;

class ExpireUnacceptedCommandes extends Command
{
    protected $signature = 'commandes:expire-unaccepted';

    protected $description = 'Annule les commandes sans réponse prestataire après 48 h';

    public function handle(OrderWorkflowService $workflow): int
    {
        $n = $workflow->expireUnaccepted();
        $this->info("Commandes expirées : {$n}");

        return self::SUCCESS;
    }
}
