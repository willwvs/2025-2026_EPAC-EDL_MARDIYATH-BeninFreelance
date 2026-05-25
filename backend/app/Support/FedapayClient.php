<?php

namespace App\Support;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class FedapayClient
{
    /**
     * Mapping interne (`mtn-benin`, `moov-benin`) vers les modes payout FedaPay.
     */
    private const PAYOUT_MODE_MAP = [
        'mtn-benin' => 'mtn_open',
        'mtn' => 'mtn_open',
        'mtn_open' => 'mtn_open',
        'moov-benin' => 'moov',
        'moov' => 'moov',
    ];

    private function http(): PendingRequest
    {
        $secret = (string) config('fedapay.secret_key', '');
        $baseUrl = (string) config('fedapay.base_url');

        return Http::baseUrl($baseUrl)
            ->withToken($secret)
            ->acceptJson()
            ->asJson()
            ->timeout(30);
    }

    /**
     * Crée une transaction (collecte) FedaPay.
     *
     * @return array{ok: bool, transaction?: array, message?: string}
     */
    public function createTransaction(array $payload): array
    {
        if ((string) config('fedapay.secret_key', '') === '') {
            return ['ok' => false, 'message' => 'FEDAPAY_SECRET_KEY non configurée.'];
        }

        try {
            $response = $this->http()->post('/v1/transactions', $payload);

            return $this->normalize($response, 'transaction', 'Création transaction échouée.');
        } catch (\Throwable $e) {
            Log::error('FedaPay createTransaction exception', ['e' => $e->getMessage()]);

            return ['ok' => false, 'message' => 'Service FedaPay indisponible.'];
        }
    }

    /**
     * Génère un token + URL de paiement pour une transaction existante.
     *
     * @return array{ok: bool, token?: string, url?: string, message?: string}
     */
    public function generateToken(int $transactionId): array
    {
        try {
            $response = $this->http()->post('/v1/transactions/'.$transactionId.'/token');

            $body = $response->json();
            if (! $response->successful() || empty($body['token'])) {
                Log::warning('FedaPay generateToken failed', ['status' => $response->status(), 'body' => $body]);

                return ['ok' => false, 'message' => $body['message'] ?? 'Génération du token échouée.'];
            }

            return [
                'ok' => true,
                'token' => (string) $body['token'],
                'url' => (string) ($body['url'] ?? ''),
            ];
        } catch (\Throwable $e) {
            Log::error('FedaPay generateToken exception', ['e' => $e->getMessage()]);

            return ['ok' => false, 'message' => 'Service FedaPay indisponible.'];
        }
    }

    /**
     * Récupère une transaction par son ID.
     *
     * @return array{ok: bool, transaction?: array, message?: string}
     */
    public function retrieveTransaction(int $transactionId): array
    {
        try {
            $response = $this->http()->get('/v1/transactions/'.$transactionId);

            return $this->normalize($response, 'transaction', 'Transaction introuvable.');
        } catch (\Throwable $e) {
            Log::error('FedaPay retrieveTransaction exception', ['e' => $e->getMessage()]);

            return ['ok' => false, 'message' => 'Service FedaPay indisponible.'];
        }
    }

    /**
     * Crée un payout (virement de ta balance vers un numéro Mobile Money).
     * Utilisé pour : verser le prestataire après une commande, ou rembourser le client.
     *
     * @return array{ok: bool, payout?: array, message?: string}
     */
    public function createPayout(array $payload): array
    {
        if ((string) config('fedapay.secret_key', '') === '') {
            return ['ok' => false, 'message' => 'FEDAPAY_SECRET_KEY non configurée.'];
        }

        try {
            $response = $this->http()->post('/v1/payouts', $payload);

            return $this->normalize($response, 'payout', 'Création du payout échouée.');
        } catch (\Throwable $e) {
            Log::error('FedaPay createPayout exception', ['e' => $e->getMessage()]);

            return ['ok' => false, 'message' => 'Service FedaPay indisponible.'];
        }
    }

    /**
     * Déclenche immédiatement l'envoi d'un payout déjà créé.
     */
    public function sendPayoutNow(int $payoutId, ?array $overridePhone = null): array
    {
        $body = ['payouts' => [['id' => $payoutId]]];
        if ($overridePhone) {
            $body['payouts'][0]['phone_number'] = $overridePhone;
        }

        try {
            $response = $this->http()->put('/v1/payouts/start', $body);

            return $this->normalize($response, 'payout', 'Envoi du payout échoué.');
        } catch (\Throwable $e) {
            Log::error('FedaPay sendPayoutNow exception', ['e' => $e->getMessage()]);

            return ['ok' => false, 'message' => 'Service FedaPay indisponible.'];
        }
    }

    /**
     * Récupère un payout par son ID FedaPay.
     *
     * @return array{ok: bool, payout?: array, message?: string}
     */
    public function retrievePayout(int $payoutId): array
    {
        try {
            $response = $this->http()->get('/v1/payouts/'.$payoutId);

            return $this->normalize($response, 'payout', 'Payout introuvable.');
        } catch (\Throwable $e) {
            Log::error('FedaPay retrievePayout exception', ['e' => $e->getMessage()]);

            return ['ok' => false, 'message' => 'Service FedaPay indisponible.'];
        }
    }

    /**
     * Convertit un mode interne (mtn-benin / moov-benin) en mode payout FedaPay (mtn_open / moov).
     */
    public function mapPayoutMode(?string $internalMode): string
    {
        $key = strtolower((string) $internalMode);

        return self::PAYOUT_MODE_MAP[$key] ?? 'mtn_open';
    }

    /**
     * Vérifie la signature d'un webhook FedaPay.
     *
     * Format header (X-FEDAPAY-SIGNATURE) : "t=<timestamp>,s=<signature>[,s=...]"
     * Signature attendue : HMAC-SHA256("<timestamp>.<payload>", secret) en hex.
     *
     * @param  string|null  $secret  surcharge du secret (pour gérer payin vs payout)
     * @param  int  $tolerance  tolérance en secondes (0 = pas de vérif timestamp)
     */
    public function verifySignature(string $payload, ?string $header, ?string $secret = null, int $tolerance = 300): bool
    {
        $secret = $secret ?? (string) config('fedapay.webhook_secret_payin', '');
        if ($secret === '' || ! $header) {
            return false;
        }

        $timestamp = -1;
        $signatures = [];
        foreach (explode(',', $header) as $item) {
            $parts = explode('=', $item, 2);
            if (count($parts) !== 2) {
                continue;
            }
            [$k, $v] = $parts;
            if ($k === 't' && is_numeric($v)) {
                $timestamp = (int) $v;
            } elseif ($k === 's') {
                $signatures[] = $v;
            }
        }

        if ($timestamp <= 0 || empty($signatures)) {
            return false;
        }

        $expected = hash_hmac('sha256', $timestamp.'.'.$payload, $secret);
        $matched = false;
        foreach ($signatures as $candidate) {
            if (hash_equals($expected, $candidate)) {
                $matched = true;
                break;
            }
        }

        if (! $matched) {
            return false;
        }

        if ($tolerance > 0 && abs(time() - $timestamp) > $tolerance) {
            return false;
        }

        return true;
    }

    private function normalize(Response $response, string $entity, string $defaultError): array
    {
        $body = $response->json();
        if (! $response->successful()) {
            Log::warning('FedaPay '.$entity.' HTTP '.$response->status(), ['body' => $body]);

            return [
                'ok' => false,
                'message' => is_array($body) ? ($body['message'] ?? $defaultError) : $defaultError,
            ];
        }

        $data = $body['v1/'.$entity] ?? $body[$entity] ?? $body;

        return ['ok' => true, $entity => is_array($data) ? $data : []];
    }
}
