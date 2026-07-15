<?php

namespace App\Console\Commands;

use App\Models\Prestataire;
use App\Models\Service;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;

class SeedSubscriptionDemo extends Command
{
    protected $signature = 'demo:seed-subscriptions
                            {--password=Demo2026! : Mot de passe des comptes démo}';

    protected $description = 'Nettoie les abonnements pending, active l’essai gratuit, crée un Pro et un essai expiré (démo).';

    public function handle(): int
    {
        if (! Schema::hasTable('prestataires')) {
            $this->error('Table prestataires introuvable.');

            return 1;
        }

        if (Schema::hasTable('abonnements')) {
            $deleted = DB::table('abonnements')->where('statut', 'pending')->delete();
            $this->info("Abonnements pending supprimés : {$deleted}");
        }

        $days = (int) config('marketplace.trial_days', 7);
        if (Schema::hasColumn('prestataires', 'trial_ends_at')) {
            $freshTrial = Prestataire::query()
                ->where(function ($q) {
                    $q->whereNull('plan')->orWhere('plan', 'gratuit')->orWhere('plan', '');
                })
                ->where(function ($q) {
                    $q->whereNull('email')
                        ->orWhere('email', '!=', 'beninfreelance.expire@gmail.com');
                })
                ->update([
                    'plan' => 'gratuit',
                    'trial_ends_at' => now()->addDays($days),
                ]);
            $this->info("Prestataires passés en essai gratuit ({$days} j) : {$freshTrial}");
        }

        $pwdCol = Schema::hasColumn('prestataires', 'mot_de_passe') ? 'mot_de_passe' : 'password';
        $password = (string) $this->option('password');
        $hash = Hash::make($password);
        $categorie = $this->defaultCategory();

        $pro = $this->upsertDemo([
            'email' => 'beninfreelance.pro@gmail.com',
            'nom' => 'Houédji',
            'prenom' => 'Kossi',
            'telephone' => '97000011',
            'localite' => 'Cotonou',
            'biographie' => 'Prestataire démo plan Pro — priorité maximale dans la recherche.',
            'plan' => 'pro',
            'plan_expires_at' => now()->addDays(30),
            'trial_ends_at' => now()->subDays(1),
            'statut_verification' => 'valide',
            'email_verified_at' => now(),
            'note_moyenne' => 0,
            $pwdCol => $hash,
        ], $pwdCol);

        Service::query()->where('prestataire_id', $pro->id)->delete();
        Service::query()->create([
            'prestataire_id' => $pro->id,
            'titre' => 'Développement web Pro (démo priorité)',
            'description' => 'Service unique du compte Pro démo — 0 avis, prioritaire face au plan gratuit.',
            'categorie' => $categorie,
            'prix_indicatif' => 150000,
            'delai_execution' => '7 jours',
        ]);
        $this->info("Pro démo #{$pro->id} · {$pro->email} · 1 service · 0 avis");

        $expired = $this->upsertDemo([
            'email' => 'beninfreelance.expire@gmail.com',
            'nom' => 'Agossou',
            'prenom' => 'Léa',
            'telephone' => '97000022',
            'localite' => 'Porto-Novo',
            'biographie' => 'Prestataire démo essai gratuit terminé — profil masqué et messagerie bloquée.',
            'plan' => 'gratuit',
            'plan_expires_at' => null,
            'trial_ends_at' => now()->subDay(),
            'statut_verification' => 'valide',
            'email_verified_at' => now(),
            'note_moyenne' => 0,
            $pwdCol => $hash,
        ], $pwdCol);

        if (! Service::query()->where('prestataire_id', $expired->id)->exists()) {
            Service::query()->create([
                'prestataire_id' => $expired->id,
                'titre' => 'Rédaction web (essai terminé)',
                'description' => 'Compte démo dont l’essai de 7 jours est terminé : impossible de discuter avec les clients.',
                'categorie' => $categorie,
                'prix_indicatif' => 25000,
                'delai_execution' => '3 jours',
            ]);
        }
        $this->info("Essai expiré #{$expired->id} · {$expired->email} · messagerie bloquée");
        $this->line("Mot de passe démo : {$password}");

        return 0;
    }

    /** @param array<string, mixed> $attrs */
    private function upsertDemo(array $attrs, string $pwdCol): Prestataire
    {
        $email = $attrs['email'];
        $existing = Prestataire::query()->where('email', $email)->first();
        if ($existing) {
            $existing->fill($attrs);
            $existing->save();

            return $existing->fresh();
        }

        return Prestataire::query()->create($attrs);
    }

    private function defaultCategory(): string
    {
        if (Schema::hasTable('categories')) {
            $nom = DB::table('categories')->orderBy('id')->value('nom');
            if (is_string($nom) && $nom !== '') {
                return $nom;
            }
        }

        $existing = Service::query()->whereNotNull('categorie')->value('categorie');

        return is_string($existing) && $existing !== '' ? $existing : 'Informatique';
    }
}
