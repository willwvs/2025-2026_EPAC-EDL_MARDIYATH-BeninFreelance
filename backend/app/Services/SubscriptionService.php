<?php

namespace App\Services;

use App\Models\Abonnement;
use App\Models\Prestataire;
use App\Support\AppMailer;
use App\Support\FedapayClient;
use Illuminate\Support\Facades\Schema;

class SubscriptionService
{
    public function __construct(
        private readonly FedapayClient $fedapay,
    ) {}

    /** @return array<string, mixed> */
    public static function planConfig(string $plan): array
    {
        $plans = config('marketplace.plans', []);

        return is_array($plans[$plan] ?? null) ? $plans[$plan] : ($plans['gratuit'] ?? [
            'label' => 'Gratuit',
            'prix' => 0,
            'services_max' => 2,
            'priority' => 0,
        ]);
    }

    public static function servicesMax(string $plan): ?int
    {
        $cfg = self::planConfig($plan);
        if (! array_key_exists('services_max', $cfg)) {
            return 2;
        }
        $max = $cfg['services_max'];

        return $max === null ? null : (int) $max;
    }

    public static function priority(string $plan): int
    {
        return (int) (self::planConfig($plan)['priority'] ?? 0);
    }

    public static function planLabel(string $plan): string
    {
        return (string) (self::planConfig($plan)['label'] ?? ucfirst($plan));
    }

    public function expireIfNeeded(Prestataire $prestataire): Prestataire
    {
        if (! Schema::hasColumn('prestataires', 'plan')) {
            return $prestataire;
        }

        $plan = (string) ($prestataire->plan ?? 'gratuit');
        if ($plan === 'gratuit' || $plan === '') {
            if ($plan === '') {
                $prestataire->plan = 'gratuit';
                $prestataire->save();
            }

            return $prestataire;
        }

        $expires = $prestataire->plan_expires_at;
        if ($expires !== null && $expires->isPast()) {
            $prestataire->update([
                'plan' => 'gratuit',
                'plan_expires_at' => null,
            ]);
            $prestataire->refresh();
        }

        return $prestataire;
    }

    public function effectivePlan(Prestataire $prestataire): string
    {
        $this->expireIfNeeded($prestataire);
        $plan = (string) ($prestataire->plan ?? 'gratuit');

        return in_array($plan, ['starter', 'pro'], true) ? $plan : 'gratuit';
    }

    public function hasPaidPlan(Prestataire $prestataire): bool
    {
        $this->expireIfNeeded($prestataire);
        $plan = (string) ($prestataire->plan ?? 'gratuit');

        return in_array($plan, ['starter', 'pro'], true)
            && $prestataire->plan_expires_at
            && $prestataire->plan_expires_at->isFuture();
    }

    public function isTrialActive(Prestataire $prestataire): bool
    {
        if (! Schema::hasColumn('prestataires', 'trial_ends_at')) {
            return true;
        }

        $ends = $prestataire->trial_ends_at;

        return $ends !== null && $ends->isFuture();
    }

    /** Messagerie autorisée si plan payant actif ou essai gratuit encore en cours. */
    public function canMessage(Prestataire $prestataire): bool
    {
        if ($this->hasPaidPlan($prestataire)) {
            return true;
        }

        return $this->isTrialActive($prestataire);
    }

    /** Visible dans la recherche / fiches clients (même règle que la messagerie). */
    public function isDiscoverable(Prestataire $prestataire): bool
    {
        return $this->canMessage($prestataire);
    }

    /**
     * SQL : prestataire visible (plan payant actif OU essai en cours).
     * Retourne null si les colonnes n'existent pas (pas de filtre).
     */
    public static function discoverableSqlCondition(string $alias = 'p'): ?string
    {
        if (! Schema::hasColumn('prestataires', 'plan')) {
            return null;
        }

        $plan = $alias.'.plan';
        $exp = $alias.'.plan_expires_at';
        $paid = "({$plan} IN ('starter','pro') AND {$exp} IS NOT NULL AND {$exp} > NOW())";

        if (! Schema::hasColumn('prestataires', 'trial_ends_at')) {
            return $paid;
        }

        $trial = $alias.'.trial_ends_at';

        return "({$paid} OR ({$trial} IS NOT NULL AND {$trial} > NOW()))";
    }

    public function messagingBlockedMessage(): string
    {
        $days = (int) config('marketplace.trial_days', 7);

        return 'Votre essai gratuit de '.$days.' jours est terminé. Souscrivez à un plan Starter ou Pro pour réapparaître dans la recherche et discuter avec les clients.';
    }

    public function assignTrial(Prestataire $prestataire): void
    {
        if (! Schema::hasColumn('prestataires', 'trial_ends_at')) {
            return;
        }

        $days = (int) config('marketplace.trial_days', 7);
        $prestataire->update([
            'plan' => 'gratuit',
            'plan_expires_at' => null,
            'trial_ends_at' => now()->addDays($days),
        ]);
        $prestataire->refresh();
    }

    /** @return array<string, mixed> */
    public function statusPayload(Prestataire $prestataire): array
    {
        $plan = $this->effectivePlan($prestataire);
        $history = [];
        if (Schema::hasTable('abonnements')) {
            $history = Abonnement::query()
                ->where('prestataire_id', $prestataire->id)
                ->orderByDesc('id')
                ->limit(10)
                ->get()
                ->map(fn (Abonnement $a) => [
                    'id' => $a->id,
                    'plan' => $a->plan,
                    'plan_label' => self::planLabel($a->plan),
                    'montant_fcfa' => (int) $a->montant_fcfa,
                    'statut' => $a->statut,
                    'starts_at' => $a->starts_at?->toIso8601String(),
                    'ends_at' => $a->ends_at?->toIso8601String(),
                    'created_at' => $a->created_at?->toIso8601String(),
                ])
                ->values()
                ->all();
        }

        $catalog = [];
        foreach (['starter', 'pro'] as $p) {
            $c = self::planConfig($p);
            $catalog[$p] = [
                'plan' => $p,
                'label' => $c['label'] ?? ucfirst($p),
                'prix' => (int) ($c['prix'] ?? 0),
                'services_max' => $c['services_max'] ?? null,
                'priority' => (int) ($c['priority'] ?? 0),
                'is_current' => $plan === $p && $this->hasPaidPlan($prestataire),
            ];
        }

        $trialActive = $this->isTrialActive($prestataire);
        $messagingAllowed = $this->canMessage($prestataire);

        return [
            'plan' => $plan,
            'plan_label' => self::planLabel($plan),
            'plan_expires_at' => $prestataire->plan_expires_at?->toIso8601String(),
            'trial_ends_at' => $prestataire->trial_ends_at?->toIso8601String(),
            'trial_active' => $trialActive,
            'trial_days' => (int) config('marketplace.trial_days', 7),
            'messaging_allowed' => $messagingAllowed,
            'messaging_blocked_message' => $messagingAllowed ? null : $this->messagingBlockedMessage(),
            'services_max' => self::servicesMax($plan),
            'priority' => self::priority($plan),
            'subscription_days' => (int) config('marketplace.subscription_days', 30),
            'catalog' => $catalog,
            'history' => $history,
        ];
    }

    /**
     * @return array{ok: bool, payment_url?: ?string, token?: ?string, abonnement_id?: int, message?: string}
     */
    public function checkout(Prestataire $prestataire, string $plan): array
    {
        if (! in_array($plan, ['starter', 'pro'], true)) {
            return ['ok' => false, 'message' => 'Plan invalide.'];
        }

        if ($prestataire->statut_verification !== 'valide') {
            return ['ok' => false, 'message' => 'Votre dossier doit être validé avant de souscrire.'];
        }

        $this->expireIfNeeded($prestataire);

        $current = $this->effectivePlan($prestataire);
        if ($this->hasPaidPlan($prestataire) && $current === $plan) {
            return [
                'ok' => false,
                'message' => 'Vous êtes déjà abonné au plan '.self::planLabel($plan).'. Choisissez un autre plan ou attendez le renouvellement.',
            ];
        }

        if (Schema::hasTable('abonnements')) {
            Abonnement::query()
                ->where('prestataire_id', $prestataire->id)
                ->where('statut', 'pending')
                ->update(['statut' => 'echoue']);
        }

        $cfg = self::planConfig($plan);
        $amount = (int) ($cfg['prix'] ?? 0);
        if ($amount < 1) {
            return ['ok' => false, 'message' => 'Montant d\'abonnement invalide.'];
        }

        $days = (int) config('marketplace.subscription_days', 30);
        $phone = preg_replace('/\D+/', '', (string) $prestataire->telephone) ?: null;
        $merchantRef = 'SUB-'.$prestataire->id.'-'.$plan.'-'.now()->format('YmdHis');

        $abonnement = Abonnement::query()->create([
            'prestataire_id' => $prestataire->id,
            'plan' => $plan,
            'montant_fcfa' => $amount,
            'periode_jours' => $days,
            'statut' => 'pending',
        ]);

        $create = $this->fedapay->createTransaction([
            'description' => 'Abonnement '.self::planLabel($plan).' #'.$abonnement->id,
            'amount' => $amount,
            'currency' => ['iso' => (string) config('fedapay.currency', 'XOF')],
            'callback_url' => url('/payments/return?abonnement_id='.$abonnement->id),
            'merchant_reference' => $merchantRef,
            'custom_metadata' => [
                'type' => 'abonnement',
                'abonnement_id' => $abonnement->id,
                'prestataire_id' => $prestataire->id,
                'plan' => $plan,
            ],
            'customer' => [
                'firstname' => $prestataire->prenom ?: 'Prestataire',
                'lastname' => $prestataire->nom ?: '-',
                'email' => $prestataire->email,
                'phone_number' => $phone ? [
                    'number' => $phone,
                    'country' => (string) config('fedapay.country', 'bj'),
                ] : null,
            ],
        ]);

        if (! ($create['ok'] ?? false)) {
            $abonnement->update(['statut' => 'echoue']);

            return ['ok' => false, 'message' => $create['message'] ?? 'Paiement indisponible.'];
        }

        $transaction = $create['transaction'] ?? [];
        $transactionId = (int) ($transaction['id'] ?? 0);
        if ($transactionId === 0) {
            $abonnement->update(['statut' => 'echoue']);

            return ['ok' => false, 'message' => 'Réponse FedaPay invalide.'];
        }

        $tokenResult = $this->fedapay->generateToken($transactionId);
        if (! ($tokenResult['ok'] ?? false)) {
            $abonnement->update([
                'statut' => 'echoue',
                'fedapay_transaction_id' => (string) $transactionId,
            ]);

            return ['ok' => false, 'message' => $tokenResult['message'] ?? 'Token FedaPay indisponible.'];
        }

        $abonnement->update([
            'fedapay_transaction_id' => (string) $transactionId,
            'payment_url' => $tokenResult['url'] ?? null,
            'provider_token' => $tokenResult['token'] ?? null,
        ]);

        return [
            'ok' => true,
            'abonnement_id' => $abonnement->id,
            'payment_url' => $tokenResult['url'] ?? null,
            'token' => $tokenResult['token'] ?? null,
        ];
    }

    public function handlePayinWebhook(array $payload): bool
    {
        $envelope = $this->unwrapFedapayEvent($payload);
        $eventName = $envelope['name'];
        $entity = $envelope['entity'];
        $transactionId = (string) ($entity['id'] ?? '');
        $status = (string) ($entity['status'] ?? '');

        if ($transactionId === '') {
            return false;
        }

        $abonnement = Abonnement::query()->where('fedapay_transaction_id', $transactionId)->first();
        if (! $abonnement) {
            $meta = $entity['custom_metadata'] ?? $entity['metadata'] ?? [];
            if (is_array($meta) && ! empty($meta['abonnement_id'])) {
                $abonnement = Abonnement::query()->find((int) $meta['abonnement_id']);
            }
        }

        if (! $abonnement) {
            return false;
        }

        $log = $abonnement->webhook_log ?? [];
        $log[] = ['at' => now()->toIso8601String(), 'event' => $eventName, 'status' => $status, 'payload' => $payload];
        $abonnement->webhook_log = $log;

        $isApproved = $eventName === 'transaction.approved' || in_array($status, ['approved', 'transferred'], true);

        if ($isApproved) {
            if ($abonnement->statut === 'paye') {
                $abonnement->save();

                return true;
            }

            $this->activate($abonnement);

            return true;
        }

        $cancelled = in_array($eventName, ['transaction.canceled', 'transaction.declined'], true)
            || in_array($status, ['canceled', 'declined', 'expired'], true);

        if ($cancelled && $abonnement->statut !== 'paye') {
            $abonnement->update(['statut' => 'echoue', 'webhook_log' => $abonnement->webhook_log]);

            return true;
        }

        $abonnement->save();

        return true;
    }

    public function syncPaymentStatus(int $abonnementId): void
    {
        $abonnement = Abonnement::query()->find($abonnementId);
        if (! $abonnement || $abonnement->statut === 'paye') {
            return;
        }

        if (! $abonnement->fedapay_transaction_id) {
            return;
        }

        $result = $this->fedapay->retrieveTransaction((int) $abonnement->fedapay_transaction_id);
        if (! ($result['ok'] ?? false)) {
            return;
        }

        $this->handlePayinWebhook([
            'name' => 'sync.manual',
            'entity' => $result['transaction'] ?? [],
        ]);
    }

    public function activate(Abonnement $abonnement): void
    {
        $prestataire = Prestataire::query()->find($abonnement->prestataire_id);
        if (! $prestataire) {
            return;
        }

        $this->expireIfNeeded($prestataire);
        $days = (int) ($abonnement->periode_jours ?: config('marketplace.subscription_days', 30));
        $base = now();

        if (
            (string) $prestataire->plan === (string) $abonnement->plan
            && $prestataire->plan_expires_at
            && $prestataire->plan_expires_at->isFuture()
        ) {
            $base = $prestataire->plan_expires_at->copy();
        }

        $starts = now();
        $ends = $base->copy()->addDays($days);

        $abonnement->update([
            'statut' => 'paye',
            'starts_at' => $starts,
            'ends_at' => $ends,
            'webhook_log' => $abonnement->webhook_log,
        ]);

        $prestataire->update([
            'plan' => $abonnement->plan,
            'plan_expires_at' => $ends,
        ]);

        AppMailer::sendAbonnementConfirme(
            $prestataire->email,
            $prestataire->prenom,
            (string) $abonnement->plan,
            self::planLabel((string) $abonnement->plan),
            $ends
        );
    }

    public function expireAllDue(): int
    {
        if (! Schema::hasColumn('prestataires', 'plan')) {
            return 0;
        }

        return Prestataire::query()
            ->whereIn('plan', ['starter', 'pro'])
            ->whereNotNull('plan_expires_at')
            ->where('plan_expires_at', '<', now())
            ->update([
                'plan' => 'gratuit',
                'plan_expires_at' => null,
            ]);
    }

    /** @return array{name: string, entity: array<string, mixed>} */
    private function unwrapFedapayEvent(array $payload): array
    {
        $name = (string) ($payload['name'] ?? '');
        $entity = $payload['entity'] ?? $payload['data'] ?? $payload['object'] ?? null;

        if ($name === '' && isset($payload['event']) && is_array($payload['event'])) {
            $name = (string) ($payload['event']['name'] ?? '');
            $entity = $entity ?? ($payload['event']['object'] ?? $payload['event']['data'] ?? $payload['event']['entity'] ?? null);
        }

        if ($name === '' && isset($payload['event']) && is_string($payload['event'])) {
            $name = (string) $payload['event'];
        }

        return [
            'name' => $name,
            'entity' => is_array($entity) ? $entity : [],
        ];
    }
}
