<?php

namespace App\Http\Controllers;

use App\Services\OrderWorkflowService;
use App\Support\FedapayClient;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;

class PaymentWebhookController extends Controller
{
    public function __construct(
        private readonly OrderWorkflowService $workflow,
        private readonly FedapayClient $fedapay,
    ) {}

    public function payinWebhook(Request $request)
    {
        if (! $this->verifyWebhook($request, 'payin')) {
            Log::warning('FedaPay payin webhook : signature invalide.', [
                'header' => $request->header('X-FEDAPAY-SIGNATURE'),
                'has_secret' => (string) config('fedapay.webhook_secret_payin', '') !== '',
            ]);

            return response()->json(['ok' => false, 'message' => 'Signature invalide.'], 400);
        }

        try {
            $this->workflow->handlePayinWebhook($request->all());
        } catch (\Throwable $e) {
            Log::error('FedaPay payin webhook : exception traitement', [
                'e' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
        }

        return response()->json(['ok' => true]);
    }

    public function payoutWebhook(Request $request)
    {
        if (! $this->verifyWebhook($request, 'payout')) {
            Log::warning('FedaPay payout webhook : signature invalide.', [
                'header' => $request->header('X-FEDAPAY-SIGNATURE'),
                'has_secret' => (string) config('fedapay.webhook_secret_payout', '') !== '',
            ]);

            return response()->json(['ok' => false, 'message' => 'Signature invalide.'], 400);
        }

        try {
            $this->workflow->handlePayoutWebhook($request->all());
        } catch (\Throwable $e) {
            Log::error('FedaPay payout webhook : exception traitement', [
                'e' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
        }

        return response()->json(['ok' => true]);
    }

    public function paymentReturn(Request $request)
    {
        $commandeId = (int) $request->query('commande_id', 0);
        if ($commandeId > 0) {
            try {
                $this->workflow->syncPaymentStatus($commandeId);
            } catch (\Throwable $e) {
                Log::warning('paymentReturn sync failed', ['e' => $e->getMessage()]);
            }
        }

        $htmlPath = base_path('../payments-return.html');
        if (File::exists($htmlPath)) {
            return response(File::get($htmlPath), 200, ['Content-Type' => 'text/html; charset=UTF-8']);
        }

        $redirect = '/dashboard/client?section=commandes'.($commandeId ? '&commande_id='.$commandeId : '');

        return response(
            '<!DOCTYPE html><html lang="fr"><head><meta charset="utf-8"><meta http-equiv="refresh" content="2;url='.$redirect.'"><title>Paiement</title></head><body style="font-family:sans-serif;padding:2rem;background:#0A0A0F;color:#F0EDE8;"><p>Paiement en cours de confirmation. Redirection…</p><p><a href="'.$redirect.'" style="color:#F5A623;">Retour aux commandes</a></p></body></html>',
            200,
            ['Content-Type' => 'text/html; charset=UTF-8']
        );
    }

    private function verifyWebhook(Request $request, string $kind): bool
    {
        $secret = $kind === 'payout'
            ? (string) config('fedapay.webhook_secret_payout', '')
            : (string) config('fedapay.webhook_secret_payin', '');

        if ($secret === '') {
            return true;
        }

        return $this->fedapay->verifySignature(
            $request->getContent(),
            $request->header('X-FEDAPAY-SIGNATURE'),
            $secret,
        );
    }
}
