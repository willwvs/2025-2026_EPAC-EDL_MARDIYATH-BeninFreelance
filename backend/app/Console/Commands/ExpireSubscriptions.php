<?php

namespace App\Console\Commands;

use App\Services\SubscriptionService;
use Illuminate\Console\Command;

class ExpireSubscriptions extends Command
{
    protected $signature = 'subscriptions:expire';

    protected $description = 'Repasse en plan gratuit les prestataires dont l\'abonnement a expiré';

    public function handle(SubscriptionService $subscriptions): int
    {
        $n = $subscriptions->expireAllDue();
        $this->info("Abonnements expirés : {$n}");

        return self::SUCCESS;
    }
}
