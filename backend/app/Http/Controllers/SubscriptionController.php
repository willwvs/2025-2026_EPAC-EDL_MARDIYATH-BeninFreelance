<?php

namespace App\Http\Controllers;

use App\Models\Abonnement;
use App\Models\Prestataire;
use App\Services\SubscriptionService;
use Illuminate\Http\Request;

class SubscriptionController extends Controller
{
    public function __construct(
        private readonly SubscriptionService $subscriptions,
    ) {}

    public function show(Request $request)
    {
        $id = (int) $request->session()->get('auth_id');
        $presta = Prestataire::query()->find($id);
        if (! $presta) {
            return response()->json(['ok' => false], 404);
        }

        return response()->json([
            'ok' => true,
            ...$this->subscriptions->statusPayload($presta),
        ]);
    }

    public function checkout(Request $request)
    {
        $id = (int) $request->session()->get('auth_id');
        $presta = Prestataire::query()->find($id);
        if (! $presta) {
            return response()->json(['ok' => false], 404);
        }

        $data = $request->validate([
            'plan' => 'required|in:starter,pro',
        ]);

        $result = $this->subscriptions->checkout($presta, $data['plan']);
        if (! ($result['ok'] ?? false)) {
            return response()->json([
                'ok' => false,
                'message' => $result['message'] ?? 'Souscription impossible.',
            ], 422);
        }

        return response()->json([
            'ok' => true,
            'payment_url' => $result['payment_url'] ?? null,
            'abonnement_id' => $result['abonnement_id'] ?? null,
        ]);
    }

    public function statusPublic(Request $request, int $id)
    {
        $abonnement = Abonnement::query()->find($id);
        if (! $abonnement) {
            return response()->json(['ok' => false, 'message' => 'Abonnement introuvable.'], 404);
        }

        return response()->json([
            'ok' => true,
            'abonnement' => [
                'id' => $abonnement->id,
                'plan' => $abonnement->plan,
                'plan_label' => SubscriptionService::planLabel($abonnement->plan),
                'montant_fcfa' => (int) $abonnement->montant_fcfa,
                'statut' => $abonnement->statut,
                'ends_at' => $abonnement->ends_at?->toIso8601String(),
            ],
        ]);
    }
}
