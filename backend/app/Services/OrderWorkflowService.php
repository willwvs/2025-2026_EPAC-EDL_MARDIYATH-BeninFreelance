<?php

namespace App\Services;

use App\Models\Avis;
use App\Models\Client;
use App\Models\Commande;
use App\Models\CommandeLivraison;
use App\Models\Paiement;
use App\Models\Payout;
use App\Models\Prestataire;
use App\Models\Service;
use App\Services\OrderEventNotifier;
use App\Support\AppMailer;
use App\Support\FedapayClient;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class OrderWorkflowService
{
    public function __construct(
        private readonly FedapayClient $fedapay,
        private readonly OrderEventNotifier $events,
    ) {}

    public function computeCommission(int $montantFcfa): array
    {
        $rate = (float) config('marketplace.commission_rate', 0);
        $commission = (int) round($montantFcfa * $rate);
        $net = max(0, $montantFcfa - $commission);

        return ['commission_fcfa' => $commission, 'net_prestataire_fcfa' => $net];
    }

    /**
     * @return array{ok: bool, commande?: Commande, message?: string}
     */
    public function propose(int $clientId, array $data): array
    {
        if (! Schema::hasTable('commandes')) {
            return ['ok' => false, 'message' => 'Module commandes indisponible.'];
        }

        $service = Service::query()
            ->where('id', (int) $data['service_id'])
            ->where('prestataire_id', (int) $data['prestataire_id'])
            ->first();

        if (! $service) {
            return ['ok' => false, 'message' => 'Service introuvable.'];
        }

        $prestataire = Prestataire::query()->find((int) $data['prestataire_id']);
        if (! $prestataire || ($prestataire->statut_verification ?? '') !== 'valide') {
            return ['ok' => false, 'message' => 'Prestataire non disponible.'];
        }

        $montant = (int) $data['montant_fcfa'];
        if ($montant < 500) {
            return ['ok' => false, 'message' => 'Montant minimum : 500 FCFA.'];
        }

        $fees = $this->computeCommission($montant);

        $commande = Commande::query()->create([
            'client_id' => $clientId,
            'prestataire_id' => (int) $data['prestataire_id'],
            'service_id' => (int) $data['service_id'],
            'titre' => (string) $data['titre'],
            'description' => (string) $data['description'],
            'montant_fcfa' => $montant,
            'delai_jours' => (int) $data['delai_jours'],
            'statut' => 'attente_prestataire',
            'commission_fcfa' => $fees['commission_fcfa'],
            'net_prestataire_fcfa' => $fees['net_prestataire_fcfa'],
        ]);

        $prestataire = Prestataire::query()->find($commande->prestataire_id);
        if ($prestataire) {
            AppMailer::sendCommandeProposee($prestataire->email, $prestataire->prenom, $commande);
        }

        $this->events->post($commande, 'client', OrderEventNotifier::KIND_EVENT,
            'Nouvelle commande proposée : '.$commande->titre,
            ['event' => 'order.proposed', 'delai_jours' => (int) $commande->delai_jours]
        );

        return ['ok' => true, 'commande' => $commande];
    }

    public function accept(Commande $commande, int $prestataireId): array
    {
        if ($commande->prestataire_id !== $prestataireId || $commande->statut !== 'attente_prestataire') {
            return ['ok' => false, 'message' => 'Action non autorisée.'];
        }

        $prestataire = Prestataire::query()->find($prestataireId);
        if (! $prestataire?->mm_phone || ! $prestataire?->mm_mode) {
            return ['ok' => false, 'message' => 'Renseignez votre numéro Mobile Money et l’opérateur dans votre profil.'];
        }

        $commande->update([
            'statut' => 'attente_paiement',
            'acceptee_at' => now(),
        ]);

        $client = Client::query()->find($commande->client_id);
        if ($client) {
            AppMailer::sendCommandeAcceptee($client->email, $client->prenom, $commande);
        }

        $this->events->post($commande, 'prestataire', OrderEventNotifier::KIND_EVENT,
            'Commande acceptée — en attente de paiement.',
            ['event' => 'order.accepted']
        );

        return ['ok' => true, 'commande' => $commande->fresh()];
    }

    public function refuse(Commande $commande, int $prestataireId, ?string $motif): array
    {
        if ($commande->prestataire_id !== $prestataireId || $commande->statut !== 'attente_prestataire') {
            return ['ok' => false, 'message' => 'Action non autorisée.'];
        }

        $commande->update([
            'statut' => 'annulee',
            'motif_refus' => $motif ?: 'Refusée par le prestataire.',
        ]);

        $this->events->post($commande, 'prestataire', OrderEventNotifier::KIND_EVENT,
            'Commande refusée par le prestataire.',
            ['event' => 'order.refused', 'motif' => $motif]
        );

        return ['ok' => true, 'commande' => $commande->fresh()];
    }

    public function cancelBeforePayment(Commande $commande, int $clientId): array
    {
        if ($commande->client_id !== $clientId || ! in_array($commande->statut, ['attente_prestataire', 'attente_paiement'], true)) {
            return ['ok' => false, 'message' => 'Annulation impossible.'];
        }

        $commande->update([
            'statut' => 'annulee',
            'motif_annulation' => 'Annulée par le client.',
        ]);

        $this->events->post($commande, 'client', OrderEventNotifier::KIND_EVENT,
            'Commande annulée par le client.',
            ['event' => 'order.cancelled']
        );

        return ['ok' => true, 'commande' => $commande->fresh()];
    }

    public function initPayment(Commande $commande, int $clientId): array
    {
        if ($commande->client_id !== $clientId || $commande->statut !== 'attente_paiement') {
            return ['ok' => false, 'message' => 'Paiement non disponible pour cette commande.'];
        }

        $client = Client::query()->find($clientId);
        if (! $client) {
            return ['ok' => false, 'message' => 'Client introuvable.'];
        }

        $existing = Paiement::query()
            ->where('commande_id', $commande->id)
            ->where('statut', 'pending')
            ->whereNotNull('payment_url')
            ->orderByDesc('id')
            ->first();

        if ($existing?->payment_url) {
            return [
                'ok' => true,
                'payment_url' => $existing->payment_url,
                'token' => $existing->provider_token,
            ];
        }

        $phone = preg_replace('/\D+/', '', (string) $client->telephone) ?: null;
        $merchantRef = 'CMD-'.$commande->id.'-'.now()->format('YmdHis');

        $create = $this->fedapay->createTransaction([
            'description' => 'Commande #'.$commande->id.' - '.Str::limit($commande->titre, 80),
            'amount' => (int) $commande->montant_fcfa,
            'currency' => ['iso' => (string) config('fedapay.currency', 'XOF')],
            'callback_url' => url('/payments/return?commande_id='.$commande->id),
            'merchant_reference' => $merchantRef,
            'custom_metadata' => [
                'commande_id' => $commande->id,
                'client_id' => $commande->client_id,
                'prestataire_id' => $commande->prestataire_id,
            ],
            'customer' => [
                'firstname' => $client->prenom ?: 'Client',
                'lastname' => $client->nom ?: '-',
                'email' => $client->email,
                'phone_number' => $phone ? [
                    'number' => $phone,
                    'country' => (string) config('fedapay.country', 'bj'),
                ] : null,
            ],
        ]);

        if (! ($create['ok'] ?? false)) {
            return ['ok' => false, 'message' => $create['message'] ?? 'Paiement indisponible.'];
        }

        $transaction = $create['transaction'] ?? [];
        $transactionId = (int) ($transaction['id'] ?? 0);
        if ($transactionId === 0) {
            return ['ok' => false, 'message' => 'Réponse FedaPay invalide.'];
        }

        $tokenResult = $this->fedapay->generateToken($transactionId);
        if (! ($tokenResult['ok'] ?? false)) {
            return ['ok' => false, 'message' => $tokenResult['message'] ?? 'Token FedaPay indisponible.'];
        }

        Paiement::query()->create([
            'commande_id' => $commande->id,
            'provider' => 'fedapay',
            'provider_ref' => (string) $transactionId,
            'provider_token' => $tokenResult['token'] ?? null,
            'payment_url' => $tokenResult['url'] ?? null,
            'montant_fcfa' => $commande->montant_fcfa,
            'statut' => 'pending',
        ]);

        return [
            'ok' => true,
            'payment_url' => $tokenResult['url'] ?? null,
            'token' => $tokenResult['token'] ?? null,
        ];
    }

    /**
     * Traite un événement webhook FedaPay (transaction.approved, transaction.canceled, ...).
     */
    public function handlePayinWebhook(array $payload): void
    {
        $envelope = $this->unwrapFedapayEvent($payload);
        $eventName = $envelope['name'];
        $entity = $envelope['entity'];
        $transactionId = (string) ($entity['id'] ?? '');
        $status = (string) ($entity['status'] ?? '');

        if ($transactionId === '') {
            Log::warning('FedaPay webhook sans transaction id', ['payload' => $payload]);

            return;
        }

        $paiement = Paiement::query()->where('provider_ref', $transactionId)->first();
        if (! $paiement) {
            Log::warning('Payin webhook: paiement inconnu', ['ref' => $transactionId]);

            return;
        }

        $log = $paiement->webhook_log ?? [];
        $log[] = ['at' => now()->toIso8601String(), 'event' => $eventName, 'status' => $status, 'payload' => $payload];
        $paiement->webhook_log = $log;

        $isApproved = $eventName === 'transaction.approved' || in_array($status, ['approved', 'transferred'], true);

        if ($isApproved) {
            if ($paiement->statut === 'paid') {
                $paiement->save();

                return;
            }

            $paiement->update([
                'statut' => 'paid',
                'frais_fcfa' => (int) ($entity['fees'] ?? 0),
                'moyen' => (string) ($entity['mode'] ?? $paiement->moyen),
                'paid_at' => now(),
                'webhook_log' => $paiement->webhook_log,
            ]);

            $commande = Commande::query()->find($paiement->commande_id);
            if ($commande && $commande->statut === 'attente_paiement') {
                $commande->update([
                    'statut' => 'payee',
                    'payee_at' => now(),
                ]);

                $prestataire = Prestataire::query()->find($commande->prestataire_id);
                if ($prestataire) {
                    AppMailer::sendCommandePayee($prestataire->email, $prestataire->prenom, $commande);
                }

                $this->events->post($commande->fresh(), 'client', OrderEventNotifier::KIND_EVENT,
                    'Paiement reçu — le travail peut commencer.',
                    ['event' => 'order.paid', 'montant_fcfa' => (int) $commande->montant_fcfa]
                );
            }

            return;
        }

        $cancelled = in_array($eventName, ['transaction.canceled', 'transaction.declined'], true)
            || in_array($status, ['canceled', 'declined', 'expired'], true);

        if ($cancelled && $paiement->statut !== 'paid') {
            $paiement->update(['statut' => 'cancelled', 'webhook_log' => $paiement->webhook_log]);

            return;
        }

        $paiement->save();
    }

    /**
     * Force la synchronisation d'un paiement avec FedaPay (utile au retour callback ou en debug).
     */
    public function syncPaymentStatus(int $commandeId): void
    {
        $paiement = Paiement::query()
            ->where('commande_id', $commandeId)
            ->whereNotNull('provider_ref')
            ->orderByDesc('id')
            ->first();

        if (! $paiement || $paiement->statut === 'paid') {
            return;
        }

        $result = $this->fedapay->retrieveTransaction((int) $paiement->provider_ref);
        if (! ($result['ok'] ?? false)) {
            return;
        }

        $this->handlePayinWebhook([
            'name' => 'sync.manual',
            'entity' => $result['transaction'] ?? [],
        ]);
    }

    /**
     * @param  array<int, UploadedFile>  $files
     */
    public function deliver(Commande $commande, int $prestataireId, ?string $message, array $files = []): array
    {
        if ($commande->prestataire_id !== $prestataireId || ! in_array($commande->statut, ['payee', 'en_revision'], true)) {
            return ['ok' => false, 'message' => 'Livraison non autorisée.'];
        }

        $files = array_values(array_filter($files));
        if (trim((string) $message) === '' && empty($files)) {
            return ['ok' => false, 'message' => 'Ajoutez un message ou au moins un fichier.'];
        }

        $version = (int) CommandeLivraison::query()->where('commande_id', $commande->id)->max('version') + 1;

        $stored = [];
        foreach ($files as $f) {
            $info = $this->storeDeliveryFile($f);
            if ($info['path']) {
                $stored[] = $info;
            }
        }

        $first = $stored[0] ?? ['path' => null, 'name' => null, 'size' => null];

        $livraison = CommandeLivraison::query()->create([
            'commande_id' => $commande->id,
            'version' => $version,
            'message' => $message,
            'attachments' => $stored ?: null,
            'attachment_path' => $first['path'],
            'attachment_name' => $first['name'],
            'attachment_size' => $first['size'],
        ]);

        $days = (int) config('marketplace.auto_release_days', 14);
        $commande->update([
            'statut' => 'livree',
            'livree_at' => now(),
            'review_until' => now()->addDays($days),
            'reminders_sent' => [],
        ]);

        $client = Client::query()->find($commande->client_id);
        if ($client) {
            AppMailer::sendCommandeLivree($client->email, $client->prenom, $commande);
        }

        $attachmentsPayload = array_map(function ($a, $i) use ($commande, $livraison) {
            return [
                'name' => $a['name'] ?? 'fichier',
                'size' => (int) ($a['size'] ?? 0),
                'mime' => $a['mime'] ?? null,
                'is_image' => isset($a['mime']) && is_string($a['mime']) && str_starts_with($a['mime'], 'image/'),
                'url' => '/api/client/commandes/'.$commande->id.'/livraisons/'.$livraison->id.'/fichier?i='.$i,
                'url_prestataire' => '/api/prestataire/commandes/'.$commande->id.'/livraisons/'.$livraison->id.'/fichier?i='.$i,
            ];
        }, $stored, array_keys($stored));

        $this->events->post(
            $commande->fresh(),
            'prestataire',
            OrderEventNotifier::KIND_DELIVERY,
            $message ?: 'Livraison soumise.',
            [
                'event' => 'order.delivered',
                'livraison_id' => (int) $livraison->id,
                'version' => $version,
                'review_until' => $commande->review_until?->toIso8601String(),
                'attachments' => $attachmentsPayload,
            ]
        );

        return ['ok' => true, 'commande' => $commande->fresh()];
    }

    public function requestRevision(Commande $commande, int $clientId, ?string $message): array
    {
        if ($commande->client_id !== $clientId || $commande->statut !== 'livree') {
            return ['ok' => false, 'message' => 'Demande de retouche impossible.'];
        }

        $commande->update(['statut' => 'en_revision']);

        $this->events->post(
            $commande->fresh(),
            'client',
            OrderEventNotifier::KIND_REVISION,
            $message ?: 'Le client a demandé une retouche.',
            ['event' => 'order.revision_requested']
        );

        return ['ok' => true, 'commande' => $commande->fresh(), 'message' => $message];
    }

    public function validateDelivery(Commande $commande, int $clientId): array
    {
        if ($commande->client_id !== $clientId || ! in_array($commande->statut, ['livree', 'en_revision'], true)) {
            return ['ok' => false, 'message' => 'Validation impossible.'];
        }

        return $this->completeAndPayout($commande);
    }

    public function completeAndPayout(Commande $commande): array
    {
        if (! in_array($commande->statut, ['livree', 'en_revision'], true)) {
            return ['ok' => false, 'message' => 'Commande non éligible.'];
        }

        $fees = $this->computeCommission($commande->montant_fcfa);
        $commande->update([
            'statut' => 'terminee',
            'terminee_at' => now(),
            'commission_fcfa' => $fees['commission_fcfa'],
            'net_prestataire_fcfa' => $fees['net_prestataire_fcfa'],
        ]);

        $commande = $commande->fresh();

        $payoutResult = null;
        try {
            $payoutResult = $this->releaseToProvider($commande);
        } catch (\Throwable $e) {
            Log::error('Payout exception après validation', [
                'commande_id' => $commande->id,
                'e' => $e->getMessage(),
            ]);
            $payoutResult = ['ok' => false, 'message' => 'Payout en attente (sera retraité par l\'admin).'];
        }

        $client = Client::query()->find($commande->client_id);
        if ($client) {
            AppMailer::sendCommandeTerminee($client->email, $client->prenom, $commande);
        }

        $this->events->post(
            $commande->fresh(),
            'client',
            OrderEventNotifier::KIND_EVENT,
            'Travail approuvé — paiement libéré au prestataire.',
            [
                'event' => 'order.completed',
                'net_prestataire_fcfa' => (int) $commande->net_prestataire_fcfa,
                'commission_fcfa' => (int) $commande->commission_fcfa,
                'payout_ok' => (bool) ($payoutResult['ok'] ?? false),
            ]
        );

        return [
            'ok' => true,
            'commande' => $commande->fresh(),
            'payout_ok' => (bool) ($payoutResult['ok'] ?? false),
            'payout_message' => $payoutResult['message'] ?? null,
        ];
    }

    /**
     * Verse le net prestataire (95 %) via l'API Payout FedaPay sur son numéro Mobile Money.
     * Le payout passe à `completed` quand FedaPay renvoie le webhook payout.sent
     * (ou l'admin peut forcer manuellement depuis l'onglet Versements).
     */
    public function releaseToProvider(Commande $commande): array
    {
        if ($commande->statut === 'paid_out') {
            return ['ok' => true, 'message' => 'Déjà versé.'];
        }

        if ($commande->statut !== 'terminee') {
            return ['ok' => false, 'message' => 'Commande non terminée.'];
        }

        $existing = Payout::query()
            ->where('commande_id', $commande->id)
            ->where('type', 'prestataire')
            ->whereIn('statut', ['pending', 'completed'])
            ->exists();

        if ($existing) {
            return ['ok' => true, 'message' => 'Versement déjà initié.'];
        }

        $prestataire = Prestataire::query()->find($commande->prestataire_id);
        if (! $prestataire?->mm_phone || ! $prestataire?->mm_mode) {
            Log::warning('Payout bloqué: MM prestataire manquant', ['commande_id' => $commande->id]);

            return ['ok' => false, 'message' => 'Numéro Mobile Money prestataire manquant.'];
        }

        $amount = (int) $commande->net_prestataire_fcfa;
        if ($amount < 1) {
            $commande->update(['statut' => 'paid_out', 'paid_out_at' => now()]);

            return ['ok' => true, 'message' => 'Montant nul, commande clôturée.'];
        }

        $phone = preg_replace('/\D+/', '', (string) $prestataire->mm_phone);

        return $this->createFedapayPayout(
            commande: $commande,
            type: 'prestataire',
            destinataireId: $prestataire->id,
            firstname: (string) ($prestataire->prenom ?? 'Prestataire'),
            lastname: (string) ($prestataire->nom ?? '-'),
            email: (string) ($prestataire->email ?? ''),
            phone: $phone,
            internalMode: (string) $prestataire->mm_mode,
            amount: $amount,
            description: 'Versement commande #'.$commande->id.' - '.Str::limit($commande->titre, 60),
        );
    }

    /**
     * Rembourse le client via un Payout FedaPay vers son numéro de téléphone.
     * FedaPay ne propose pas d'API "refund transaction" : on crée donc un nouveau
     * payout (sortant) depuis la balance marchande vers le client.
     */
    public function refundClient(Commande $commande, ?string $motif): array
    {
        if (! in_array($commande->statut, ['payee', 'livree', 'en_revision', 'terminee'], true)) {
            return ['ok' => false, 'message' => 'Remboursement impossible pour ce statut.'];
        }

        $client = Client::query()->find($commande->client_id);
        if (! $client) {
            return ['ok' => false, 'message' => 'Client introuvable.'];
        }

        $phone = preg_replace('/\D+/', '', (string) $client->telephone);
        if ($phone === '') {
            return ['ok' => false, 'message' => 'Numéro client manquant pour le remboursement.'];
        }

        $amount = (int) $commande->montant_fcfa;

        $result = $this->createFedapayPayout(
            commande: $commande,
            type: 'remboursement_client',
            destinataireId: $client->id,
            firstname: (string) ($client->prenom ?? 'Client'),
            lastname: (string) ($client->nom ?? '-'),
            email: (string) ($client->email ?? ''),
            phone: $phone,
            internalMode: 'mtn-benin',
            amount: $amount,
            description: 'Remboursement commande #'.$commande->id,
        );

        if (! ($result['ok'] ?? false)) {
            return $result;
        }

        $commande->update([
            'statut' => 'remboursee',
            'remboursee_at' => now(),
            'motif_annulation' => $motif ?: 'Remboursement administrateur.',
        ]);

        $this->events->post(
            $commande->fresh(),
            'system',
            OrderEventNotifier::KIND_EVENT,
            'Remboursement émis : '.($motif ?: 'décision administrateur').'.',
            [
                'event' => 'order.refunded',
                'montant_fcfa' => (int) $commande->montant_fcfa,
                'motif' => $motif,
            ]
        );

        return ['ok' => true, 'message' => $result['message'] ?? 'Remboursement initié.'];
    }

    /**
     * Webhook payout FedaPay : payout.sent, payout.failed.
     */
    public function handlePayoutWebhook(array $payload): void
    {
        $envelope = $this->unwrapFedapayEvent($payload);
        $eventName = $envelope['name'];
        $entity = $envelope['entity'];
        $ref = (string) ($entity['id'] ?? '');
        $status = (string) ($entity['status'] ?? '');

        if ($ref === '') {
            return;
        }

        $payout = Payout::query()->where('provider_ref', $ref)->first();
        if (! $payout) {
            Log::warning('Payout webhook: ref inconnue', ['ref' => $ref, 'event' => $eventName]);

            return;
        }

        $log = $payout->webhook_log ?? [];
        $log[] = ['at' => now()->toIso8601String(), 'event' => $eventName, 'status' => $status, 'payload' => $payload];
        $payout->webhook_log = $log;

        $sent = $eventName === 'payout.sent' || $status === 'sent';
        if ($sent) {
            if ($payout->statut !== 'completed') {
                $this->finalizePayoutCompletion($payout);
            }

            return;
        }

        $failed = $eventName === 'payout.failed' || in_array($status, ['failed', 'canceled'], true);
        if ($failed && $payout->statut !== 'completed') {
            $payout->update(['statut' => 'cancelled', 'webhook_log' => $payout->webhook_log]);

            return;
        }

        $payout->save();
    }

    /**
     * Marque un payout comme complété manuellement (filet de sécurité admin).
     */
    public function markPayoutCompleted(Payout $payout): array
    {
        if ($payout->statut === 'completed') {
            return ['ok' => true, 'message' => 'Déjà complété.'];
        }

        $this->finalizePayoutCompletion($payout);

        return ['ok' => true, 'message' => 'Versement marqué comme complété.'];
    }

    /**
     * Crée un payout FedaPay + l'envoie immédiatement, et enregistre la ligne en BDD.
     */
    private function createFedapayPayout(
        Commande $commande,
        string $type,
        int $destinataireId,
        string $firstname,
        string $lastname,
        string $email,
        string $phone,
        string $internalMode,
        int $amount,
        string $description,
    ): array {
        $mode = $this->fedapay->mapPayoutMode($internalMode);
        $country = (string) config('fedapay.country', 'bj');

        $create = $this->fedapay->createPayout([
            'amount' => $amount,
            'currency' => ['iso' => (string) config('fedapay.currency', 'XOF')],
            'mode' => $mode,
            'description' => $description,
            'merchant_reference' => 'PAY-'.$type.'-'.$commande->id.'-'.now()->format('YmdHis'),
            'custom_metadata' => [
                'commande_id' => $commande->id,
                'type' => $type,
                'destinataire_id' => $destinataireId,
            ],
            'customer' => [
                'firstname' => $firstname !== '' ? $firstname : 'Bénéficiaire',
                'lastname' => $lastname !== '' ? $lastname : '-',
                'email' => $email !== '' ? $email : null,
                'phone_number' => [
                    'number' => $phone,
                    'country' => $country,
                ],
            ],
        ]);

        if (! ($create['ok'] ?? false)) {
            return ['ok' => false, 'message' => $create['message'] ?? 'Création du payout refusée.'];
        }

        $payoutData = $create['payout'] ?? [];
        $fedapayPayoutId = (int) ($payoutData['id'] ?? 0);
        if ($fedapayPayoutId === 0) {
            return ['ok' => false, 'message' => 'Réponse FedaPay invalide (payout id manquant).'];
        }

        $payout = Payout::query()->create([
            'commande_id' => $commande->id,
            'provider' => 'fedapay',
            'type' => $type,
            'destinataire_id' => $destinataireId,
            'phone' => $phone,
            'withdraw_mode' => $internalMode,
            'provider_ref' => (string) $fedapayPayoutId,
            'montant_fcfa' => $amount,
            'statut' => 'pending',
        ]);

        $send = $this->fedapay->sendPayoutNow($fedapayPayoutId);
        if (! ($send['ok'] ?? false)) {
            $payout->update([
                'webhook_log' => array_merge($payout->webhook_log ?? [], [[
                    'at' => now()->toIso8601String(),
                    'event' => 'sendNow.failed',
                    'message' => $send['message'] ?? null,
                ]]),
            ]);

            return ['ok' => false, 'message' => $send['message'] ?? 'Envoi du payout refusé.'];
        }

        return ['ok' => true, 'message' => 'Versement initié (ref. FedaPay #'.$fedapayPayoutId.').'];
    }

    /**
     * Enregistre un remboursement litige en attente (FedaPay refusé ou sandbox sans solde).
     */
    private function recordManualDisputePayout(
        Commande $commande,
        int $clientId,
        string $phone,
        int $amount,
        string $resolution,
        ?string $apiError
    ): void {
        if (! Schema::hasTable('payouts')) {
            return;
        }

        $log = [];
        if ($apiError) {
            $log[] = ['at' => now()->toIso8601String(), 'event' => 'fedapay.skipped', 'message' => $apiError];
        }

        Payout::query()->create([
            'commande_id' => $commande->id,
            'provider' => 'fedapay',
            'type' => 'remboursement_client',
            'destinataire_id' => $clientId,
            'phone' => $phone,
            'withdraw_mode' => 'mtn-benin',
            'provider_ref' => null,
            'montant_fcfa' => $amount,
            'statut' => 'pending',
            'webhook_log' => $log ?: null,
        ]);
    }

    /**
     * Marque un payout comme complété et déclenche les effets (commande paid_out + mail).
     */
    private function finalizePayoutCompletion(Payout $payout): void
    {
        $payout->update(['statut' => 'completed', 'completed_at' => now()]);

        $commande = Commande::query()->find($payout->commande_id);
        if (! $commande) {
            return;
        }

        if ($payout->type === 'prestataire' && $commande->statut === 'terminee') {
            $commande->update(['statut' => 'paid_out', 'paid_out_at' => now()]);
            $prestataire = Prestataire::query()->find($commande->prestataire_id);
            if ($prestataire) {
                AppMailer::sendPayoutOk($prestataire->email, $prestataire->prenom, $commande, $payout->montant_fcfa);
            }
        }
    }

    /**
     * Ouvre un litige : la commande est gelée jusqu'à décision de l'admin.
     * Inspiré de ComeUp (réclamation → médiation → décision ferme).
     */
    public function openDispute(Commande $commande, int $clientId, string $motif): array
    {
        if ($commande->client_id !== $clientId) {
            return ['ok' => false, 'message' => 'Action non autorisée.'];
        }

        if (! in_array($commande->statut, ['payee', 'livree', 'en_revision'], true)) {
            return ['ok' => false, 'message' => 'Litige possible uniquement après paiement et avant clôture.'];
        }

        if (trim($motif) === '') {
            return ['ok' => false, 'message' => 'Précisez la raison du litige.'];
        }

        $commande->update([
            'statut' => 'litige',
            'litige_at' => now(),
            'litige_motif' => $motif,
            'litige_resolution' => null,
            'litige_resolved_at' => null,
        ]);

        $this->events->post(
            $commande->fresh(),
            'client',
            OrderEventNotifier::KIND_EVENT,
            'Litige ouvert : '.$motif,
            ['event' => 'order.dispute_opened', 'motif' => $motif]
        );

        $fresh = $commande->fresh(['client', 'prestataire']);
        $client = $fresh?->client;
        $presta = $fresh?->prestataire;
        AppMailer::sendAdminLitige(
            (int) $fresh->id,
            (string) ($fresh->titre ?? ''),
            $client ? trim($client->prenom.' '.$client->nom) : 'Client',
            $presta ? trim($presta->prenom.' '.$presta->nom) : 'Prestataire',
            (int) ($fresh->montant_fcfa ?? 0),
            $motif,
        );

        return ['ok' => true, 'commande' => $fresh];
    }

    /**
     * Résout un litige (admin uniquement).
     *
     * Résolutions possibles :
     *  - refund_full        → remboursement intégral (statut = remboursee)
     *  - refund_partial     → remboursement partiel (montant) + reste libéré au prestataire
     *  - release_to_provider → tout libéré au prestataire (statut = terminee/paid_out)
     */
    public function resolveDispute(Commande $commande, string $resolution, ?int $partialRefundFcfa, ?string $note): array
    {
        if ($commande->statut !== 'litige') {
            return ['ok' => false, 'message' => 'Commande non en litige.'];
        }

        $resolution = strtolower($resolution);
        $resolved = ['ok' => false, 'message' => 'Résolution inconnue.'];

        if ($resolution === 'refund_full') {
            $resolved = $this->disputeRefund($commande, (int) $commande->montant_fcfa, 'refund_full', $note);
        } elseif ($resolution === 'refund_partial') {
            $amount = max(1, (int) $partialRefundFcfa);
            if ($amount >= (int) $commande->montant_fcfa) {
                return ['ok' => false, 'message' => 'Pour un remboursement total utilisez refund_full.'];
            }
            $resolved = $this->disputeRefund($commande, $amount, 'refund_partial', $note);
        } elseif ($resolution === 'release_to_provider') {
            $resolved = $this->disputeRelease($commande, $note);
        }

        return $resolved;
    }

    private function disputeRefund(Commande $commande, int $amount, string $resolution, ?string $note): array
    {
        $client = Client::query()->find($commande->client_id);
        if (! $client) {
            return ['ok' => false, 'message' => 'Client introuvable.'];
        }
        $phone = preg_replace('/\D+/', '', (string) $client->telephone);
        if ($phone === '') {
            return ['ok' => false, 'message' => 'Numéro client manquant.'];
        }

        $result = $this->createFedapayPayout(
            commande: $commande,
            type: 'remboursement_client',
            destinataireId: $client->id,
            firstname: (string) ($client->prenom ?? 'Client'),
            lastname: (string) ($client->nom ?? '-'),
            email: (string) ($client->email ?? ''),
            phone: $phone,
            internalMode: 'mtn-benin',
            amount: $amount,
            description: 'Litige #'.$commande->id.' — '.$resolution,
        );

        $fedapaySkipped = false;
        if (! ($result['ok'] ?? false)) {
            if (config('marketplace.dispute_allow_db_close')) {
                $this->recordManualDisputePayout($commande, $client->id, $phone, $amount, $resolution, $result['message'] ?? null);
                $fedapaySkipped = true;
            } else {
                return $result;
            }
        }

        $partial = $resolution === 'refund_partial';
        $remaining = (int) $commande->montant_fcfa - $amount;

        $commande->update([
            'statut' => $partial ? 'terminee' : 'remboursee',
            'remboursee_at' => $partial ? null : now(),
            'terminee_at' => $partial ? now() : null,
            'litige_resolution' => $resolution,
            'litige_resolution_note' => $note,
            'litige_resolved_at' => now(),
            'motif_annulation' => $partial ? null : ($note ?: 'Remboursement litige.'),
        ]);

        // Si remboursement partiel : verser le reste au prestataire
        if ($partial && $remaining > 0) {
            $netRate = (float) config('marketplace.commission_rate', 0);
            $commission = (int) round($remaining * $netRate);
            $net = max(0, $remaining - $commission);
            $commande->update([
                'commission_fcfa' => $commission,
                'net_prestataire_fcfa' => $net,
            ]);
            try {
                $this->releaseToProvider($commande->fresh());
            } catch (\Throwable $e) {
                Log::error('Payout litige partiel échec', ['e' => $e->getMessage()]);
            }
        }

        $this->events->post(
            $commande->fresh(),
            'system',
            OrderEventNotifier::KIND_EVENT,
            $partial
                ? 'Litige clos par l\'admin : remboursement partiel de '.number_format($amount, 0, ',', ' ').' FCFA.'
                : 'Litige clos par l\'admin : remboursement intégral.',
            ['event' => 'order.dispute_resolved', 'resolution' => $resolution, 'note' => $note, 'amount' => $amount]
        );

        $msg = $fedapaySkipped
            ? 'Litige clos. Remboursement enregistré — à effectuer manuellement depuis FedaPay (voir onglet Versements).'
            : 'Litige résolu : remboursement émis.';

        return ['ok' => true, 'message' => $msg];
    }

    private function disputeRelease(Commande $commande, ?string $note): array
    {
        $fees = $this->computeCommission((int) $commande->montant_fcfa);
        $commande->update([
            'statut' => 'terminee',
            'terminee_at' => now(),
            'commission_fcfa' => $fees['commission_fcfa'],
            'net_prestataire_fcfa' => $fees['net_prestataire_fcfa'],
            'litige_resolution' => 'release_to_provider',
            'litige_resolution_note' => $note,
            'litige_resolved_at' => now(),
        ]);

        try {
            $this->releaseToProvider($commande->fresh());
        } catch (\Throwable $e) {
            Log::error('Payout litige release échec', ['e' => $e->getMessage()]);
        }

        $this->events->post(
            $commande->fresh(),
            'system',
            OrderEventNotifier::KIND_EVENT,
            'Litige clos par l\'admin : paiement libéré au prestataire.',
            ['event' => 'order.dispute_resolved', 'resolution' => 'release_to_provider', 'note' => $note]
        );

        return ['ok' => true, 'message' => 'Litige résolu : paiement libéré.'];
    }

    public function expireUnaccepted(): int
    {
        $hours = (int) config('marketplace.accept_deadline_hours', 48);
        $cutoff = now()->subHours($hours);

        return Commande::query()
            ->where('statut', 'attente_prestataire')
            ->where('created_at', '<=', $cutoff)
            ->update([
                'statut' => 'annulee',
                'motif_annulation' => 'Expirée : pas de réponse prestataire sous 48 h.',
            ]);
    }

    public function autoRelease(): int
    {
        $count = 0;
        $commandes = Commande::query()
            ->where('statut', 'livree')
            ->whereNotNull('review_until')
            ->where('review_until', '<=', now())
            ->get();

        foreach ($commandes as $commande) {
            $this->events->post(
                $commande,
                'system',
                OrderEventNotifier::KIND_EVENT,
                'Validation automatique après 14 jours sans réponse du client.',
                ['event' => 'order.auto_released']
            );
            $result = $this->completeAndPayout($commande);
            if ($result['ok'] ?? false) {
                $count++;
            }
        }

        return $count;
    }

    public function sendReviewReminders(): void
    {
        $targets = Commande::query()
            ->where('statut', 'livree')
            ->whereNotNull('review_until')
            ->get();

        foreach ($targets as $commande) {
            if (! $commande->review_until) {
                continue;
            }
            $daysLeft = (int) now()->diffInDays($commande->review_until, false);
            if (! in_array($daysLeft, [3, 1], true)) {
                continue;
            }

            $sent = is_array($commande->reminders_sent) ? $commande->reminders_sent : [];
            $key = 'd'.$daysLeft;
            if (in_array($key, $sent, true)) {
                continue;
            }

            $client = Client::query()->find($commande->client_id);
            if ($client && AppMailer::sendRappelReview($client->email, $client->prenom, $commande, $daysLeft)) {
                $sent[] = $key;
                $commande->update(['reminders_sent' => array_values(array_unique($sent))]);
            }
        }
    }

    /**
     * Normalise les différents formats de webhook FedaPay vers {name, entity}.
     */
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

    public function recalculatePrestataireRating(int $prestataireId): void
    {
        if (! Schema::hasTable('avis')) {
            return;
        }

        $avg = Avis::query()->where('prestataire_id', $prestataireId)->avg('note');
        Prestataire::query()->where('id', $prestataireId)->update([
            'note_moyenne' => round((float) ($avg ?? 0), 2),
        ]);
    }

    /**
     * @return array{path: ?string, name: ?string, size: ?int, mime: ?string}
     */
    private function storeDeliveryFile(?UploadedFile $file): array
    {
        if (! $file) {
            return ['path' => null, 'name' => null, 'size' => null, 'mime' => null];
        }

        $name = Str::uuid().'_'.preg_replace('/[^a-zA-Z0-9._-]/', '_', $file->getClientOriginalName());
        $path = $file->storeAs('commandes/livraisons', $name, 'local');

        return [
            'path' => $path,
            'name' => $file->getClientOriginalName(),
            'size' => (int) $file->getSize(),
            'mime' => $file->getMimeType() ?: null,
        ];
    }
}
