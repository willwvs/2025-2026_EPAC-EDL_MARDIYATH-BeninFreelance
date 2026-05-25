<?php

namespace App\Http\Controllers;

use App\Models\Commande;
use App\Models\Paiement;
use App\Models\Payout;
use App\Models\Prestataire;
use App\Services\OrderWorkflowService;
use App\Support\AppMailer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

class AdminController extends Controller
{
    public function __construct(
        private readonly OrderWorkflowService $orderWorkflow,
    ) {}

    public function sessionStatus()
    {
        return response()->json([
            'ok' => true,
            'authenticated' => (bool) session('admin_ok'),
        ]);
    }

    public function loginApi(Request $request)
    {
        $expected = (string) config('services.admin_password', '');
        if ($expected === '') {
            return response()->json(['ok' => false, 'message' => 'ADMIN_PASSWORD non defini dans .env'], 503);
        }

        $password = (string) $request->input('password', '');
        if (! hash_equals($expected, $password)) {
            return response()->json(['ok' => false, 'message' => 'Mot de passe incorrect'], 422);
        }

        $request->session()->regenerate();
        session(['admin_ok' => true]);

        return response()->json(['ok' => true]);
    }

    public function logoutApi(Request $request)
    {
        $request->session()->forget('admin_ok');
        $request->session()->regenerateToken();

        return response()->json(['ok' => true, 'redirect' => '/admin']);
    }

    public function listApi()
    {
        $rows = Prestataire::query()
            ->orderByDesc('id')
            ->limit(200)
            ->get()
            ->map(fn ($r) => [
                'id' => $r->id,
                'nom_complet' => trim($r->prenom.' '.$r->nom),
                'email' => $r->email,
                'statut' => $r->statut_verification ?? '',
            ]);

        return response()->json(['ok' => true, 'rows' => $rows]);
    }

    public function showApi(int $id)
    {
        $p = Prestataire::query()->where('id', $id)->first();
        if (! $p) {
            abort(404);
        }

        return response()->json([
            'ok' => true,
            'prestataire' => [
                'id' => $p->id,
                'nom' => $p->nom,
                'prenom' => $p->prenom,
                'email' => $p->email,
                'statut_verification' => $p->statut_verification,
                'motif_refus_ia' => $p->motif_refus_ia,
            ],
            'urls' => [
                'cv' => url('/admin/prestataires/'.$id.'/fichiers/cv'),
                'identite' => url('/admin/prestataires/'.$id.'/fichiers/identite'),
            ],
        ]);
    }

    public function validateApi(Request $request, int $id)
    {
        $this->applyAdminDecision($id, 'valide', $this->adminMotif($request));

        return response()->json(['ok' => true]);
    }

    public function refuseApi(Request $request, int $id)
    {
        $this->applyAdminDecision(
            $id,
            'refuse',
            $this->adminMotif($request, 'Dossier refuse par l\'administrateur.')
        );

        return response()->json(['ok' => true]);
    }

    private function applyAdminDecision(int $id, string $statut, ?string $motif): void
    {
        $p = Prestataire::query()->where('id', $id)->first();
        if (! $p) {
            return;
        }

        $payload = [
            'statut_verification' => $statut,
            'motif_refus_ia' => $motif,
        ];

        if (Schema::hasColumn('prestataires', 'admin_decided_at')) {
            $payload['admin_decided_at'] = now();
        }

        $p->update($payload);
        $p->refresh();

        if ($p->email_verified_at && ! $p->verification_email_sent_at) {
            if (AppMailer::sendPrestataireDecision($p->email, $p->prenom, $statut, $motif)) {
                $p->update(['verification_email_sent_at' => now()]);
            }
        }
    }

    private function adminMotif(Request $request, ?string $default = null): ?string
    {
        $motif = trim((string) $request->input('motif', ''));
        if ($motif !== '') {
            return $motif;
        }

        return $default;
    }

    public function listCommandes()
    {
        if (! Schema::hasTable('commandes')) {
            return response()->json(['ok' => true, 'rows' => []]);
        }

        $rows = Commande::query()
            ->orderByDesc('id')
            ->limit(200)
            ->get()
            ->map(function ($c) {
                $paiement = Paiement::query()
                    ->where('commande_id', $c->id)
                    ->where('statut', 'paid')
                    ->orderByDesc('id')
                    ->first();

                return [
                    'id' => $c->id,
                    'titre' => $c->titre,
                    'montant_fcfa' => (int) $c->montant_fcfa,
                    'statut' => $c->statut,
                    'client_id' => $c->client_id,
                    'prestataire_id' => $c->prestataire_id,
                    'fedapay_ref' => $paiement?->provider_ref,
                    'created_at' => $c->created_at?->format('d/m/Y H:i'),
                ];
            });

        return response()->json(['ok' => true, 'rows' => $rows]);
    }

    public function rembourserCommande(Request $request, int $id)
    {
        $commande = Commande::query()->find($id);
        if (! $commande) {
            return response()->json(['ok' => false, 'message' => 'Commande introuvable.'], 404);
        }

        $data = $request->validate(['motif' => 'nullable|string|max:255']);
        $result = $this->orderWorkflow->refundClient($commande, $data['motif'] ?? null);

        if (! ($result['ok'] ?? false)) {
            return response()->json(['ok' => false, 'message' => $result['message'] ?? 'Erreur'], 422);
        }

        return response()->json(['ok' => true, 'message' => $result['message'] ?? 'Remboursement initié.']);
    }

    public function listDisputes()
    {
        if (! Schema::hasTable('commandes') || ! Schema::hasColumn('commandes', 'litige_at')) {
            return response()->json(['ok' => true, 'rows' => []]);
        }

        $rows = Commande::query()
            ->whereNotNull('litige_at')
            ->orderByDesc('id')
            ->limit(200)
            ->get()
            ->map(function ($c) {
                return [
                    'id' => $c->id,
                    'titre' => $c->titre,
                    'montant_fcfa' => (int) $c->montant_fcfa,
                    'statut' => $c->statut,
                    'client_id' => $c->client_id,
                    'prestataire_id' => $c->prestataire_id,
                    'litige_at' => $c->litige_at?->format('d/m/Y H:i'),
                    'litige_motif' => $c->litige_motif,
                    'litige_resolution' => $c->litige_resolution,
                    'litige_resolved_at' => $c->litige_resolved_at?->format('d/m/Y H:i'),
                ];
            });

        return response()->json(['ok' => true, 'rows' => $rows]);
    }

    public function resolveDispute(Request $request, int $id)
    {
        $commande = Commande::query()->find($id);
        if (! $commande) {
            return response()->json(['ok' => false, 'message' => 'Commande introuvable.'], 404);
        }

        $data = $request->validate([
            'resolution' => 'required|string|in:refund_full,refund_partial,release_to_provider',
            'amount' => 'nullable|integer|min:1',
            'note' => 'nullable|string|max:2000',
        ]);

        $result = $this->orderWorkflow->resolveDispute(
            $commande,
            $data['resolution'],
            $data['amount'] ?? null,
            $data['note'] ?? null
        );

        if (! ($result['ok'] ?? false)) {
            return response()->json(['ok' => false, 'message' => $result['message'] ?? 'Erreur'], 422);
        }

        return response()->json(['ok' => true, 'message' => $result['message'] ?? 'Litige résolu.']);
    }

    public function listPayouts()
    {
        if (! Schema::hasTable('payouts')) {
            return response()->json(['ok' => true, 'rows' => []]);
        }

        $rows = Payout::query()
            ->orderByDesc('id')
            ->limit(200)
            ->get()
            ->map(function ($p) {
                $commande = Commande::query()->find($p->commande_id);
                $prestataire = $p->type === 'prestataire' ? Prestataire::query()->find($p->destinataire_id) : null;

                return [
                    'id' => $p->id,
                    'commande_id' => $p->commande_id,
                    'commande_titre' => $commande?->titre,
                    'type' => $p->type,
                    'destinataire_label' => $prestataire
                        ? trim(($prestataire->prenom ?? '').' '.($prestataire->nom ?? ''))
                        : 'Client #'.$p->destinataire_id,
                    'phone' => $p->phone,
                    'withdraw_mode' => $p->withdraw_mode,
                    'montant_fcfa' => (int) $p->montant_fcfa,
                    'statut' => $p->statut,
                    'provider_ref' => $p->provider_ref,
                    'created_at' => $p->created_at?->format('d/m/Y H:i'),
                ];
            });

        return response()->json(['ok' => true, 'rows' => $rows]);
    }

    public function markPayoutPaid(int $id)
    {
        $payout = Payout::query()->find($id);
        if (! $payout) {
            return response()->json(['ok' => false, 'message' => 'Versement introuvable.'], 404);
        }

        $result = $this->orderWorkflow->markPayoutCompleted($payout);

        return response()->json([
            'ok' => $result['ok'] ?? false,
            'message' => $result['message'] ?? null,
        ]);
    }

    public function file(int $id, string $type)
    {
        $p = Prestataire::query()->where('id', $id)->first();
        if (! $p) {
            abort(404);
        }

        $rel = match ($type) {
            'cv' => $p->chemin_cv,
            'identite' => $p->chemin_identite,
            default => null,
        };

        if (! $rel || ! Storage::disk('local')->exists($rel)) {
            abort(404);
        }

        return Storage::disk('local')->response($rel);
    }
}
