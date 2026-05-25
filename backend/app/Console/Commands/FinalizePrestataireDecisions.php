<?php

namespace App\Console\Commands;

use App\Models\Prestataire;
use App\Support\AppMailer;
use App\Support\PrestataireVerification;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;

class FinalizePrestataireDecisions extends Command
{
    protected $signature = 'prestataires:finalize-ia-decisions';

    protected $description = 'Finalise les décisions IA sans action admin (24 h) et envoie les e-mails';

    public function handle(): int
    {
        if (! Schema::hasColumn('prestataires', 'ia_decided_at')) {
            $this->warn('Colonnes de vérification absentes — exécutez la migration.');

            return self::SUCCESS;
        }

        $rows = Prestataire::query()
            ->whereNull('admin_decided_at')
            ->whereNotNull('ia_decided_at')
            ->whereIn('statut_verification', ['valide', 'refuse'])
            ->get();

        $count = 0;
        foreach ($rows as $p) {
            if (! PrestataireVerification::shouldAutoFinalize($p)) {
                continue;
            }

            if ($p->verification_email_sent_at) {
                continue;
            }

            if ($p->email_verified_at && AppMailer::sendPrestataireDecision(
                $p->email,
                $p->prenom,
                (string) $p->statut_verification,
                $p->motif_refus_ia
            )) {
                $p->update(['verification_email_sent_at' => now()]);
                $count++;
            }
        }

        $this->info("E-mails de décision envoyés : {$count}");

        return self::SUCCESS;
    }
}
