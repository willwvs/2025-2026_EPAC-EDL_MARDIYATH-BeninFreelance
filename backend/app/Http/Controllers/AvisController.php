<?php

namespace App\Http\Controllers;

use App\Models\Avis;
use App\Models\Client;
use App\Models\Commande;
use App\Models\Prestataire;
use App\Services\OrderWorkflowService;
use App\Support\AppMailer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;

class AvisController extends Controller
{
    public function __construct(
        private readonly OrderWorkflowService $workflow,
    ) {}

    public function storeForCommande(Request $request, int $commandeId)
    {
        if (! Schema::hasTable('avis') || ! Schema::hasColumn('avis', 'commande_id')) {
            return response()->json(['ok' => false, 'message' => 'Avis indisponible.'], 503);
        }

        $clientId = (int) $request->session()->get('auth_id');
        $commande = Commande::query()
            ->where('id', $commandeId)
            ->where('client_id', $clientId)
            ->first();

        if (! $commande) {
            return response()->json(['ok' => false, 'message' => 'Commande introuvable.'], 404);
        }

        if (! in_array($commande->statut, ['terminee', 'paid_out'], true)) {
            return response()->json(['ok' => false, 'message' => 'Vous pourrez noter après validation de la livraison.'], 422);
        }

        if (Avis::query()->where('commande_id', $commande->id)->exists()) {
            return response()->json(['ok' => false, 'message' => 'Vous avez déjà laissé un avis pour cette commande.'], 422);
        }

        $data = $request->validate([
            'note' => 'required|integer|min:1|max:5',
            'commentaire' => 'nullable|string|max:2000',
        ]);

        $avis = Avis::query()->create([
            'client_id' => $clientId,
            'prestataire_id' => $commande->prestataire_id,
            'commande_id' => $commande->id,
            'note' => (int) $data['note'],
            'commentaire' => $data['commentaire'] ?? null,
        ]);

        $this->workflow->recalculatePrestataireRating($commande->prestataire_id);

        return response()->json([
            'ok' => true,
            'avis' => [
                'id' => $avis->id,
                'note' => $avis->note,
                'commentaire' => $avis->commentaire,
            ],
        ]);
    }

    public function updateForCommande(Request $request, int $commandeId)
    {
        if (! Schema::hasTable('avis') || ! Schema::hasColumn('avis', 'commande_id')) {
            return response()->json(['ok' => false, 'message' => 'Avis indisponible.'], 503);
        }

        $clientId = (int) $request->session()->get('auth_id');
        $commande = Commande::query()
            ->where('id', $commandeId)
            ->where('client_id', $clientId)
            ->first();

        if (! $commande) {
            return response()->json(['ok' => false, 'message' => 'Commande introuvable.'], 404);
        }

        $avis = Avis::query()
            ->where('commande_id', $commande->id)
            ->where('client_id', $clientId)
            ->first();

        if (! $avis) {
            return response()->json(['ok' => false, 'message' => 'Aucun avis à modifier pour cette commande.'], 404);
        }

        $data = $request->validate([
            'note' => 'required|integer|min:1|max:5',
            'commentaire' => 'nullable|string|max:2000',
        ]);

        $avis->update([
            'note' => (int) $data['note'],
            'commentaire' => $data['commentaire'] ?? null,
        ]);

        $this->workflow->recalculatePrestataireRating($commande->prestataire_id);

        return response()->json([
            'ok' => true,
            'avis' => [
                'id' => $avis->id,
                'note' => (int) $avis->note,
                'commentaire' => $avis->commentaire,
            ],
        ]);
    }

    public function signaler(Request $request, int $id)
    {
        if (! Schema::hasTable('avis') || ! Schema::hasColumn('avis', 'signale')) {
            return response()->json(['ok' => false, 'message' => 'Signalement indisponible.'], 503);
        }

        $clientId = (int) $request->session()->get('auth_id');
        $avis = Avis::query()->find($id);
        if (! $avis) {
            return response()->json(['ok' => false, 'message' => 'Avis introuvable.'], 404);
        }

        if ((int) $avis->client_id === $clientId) {
            return response()->json(['ok' => false, 'message' => 'Vous ne pouvez pas signaler votre propre avis.'], 422);
        }

        if ($avis->signale) {
            return response()->json([
                'ok' => true,
                'message' => 'Cet avis a déjà été signalé. Notre équipe va l\'examiner.',
                'already' => true,
            ]);
        }

        $data = $request->validate([
            'motif' => 'nullable|string|max:500',
        ]);

        $motif = isset($data['motif']) ? trim((string) $data['motif']) : null;

        $avis->update([
            'signale' => true,
            'signale_motif' => $motif !== '' ? $motif : null,
            'signale_at' => now(),
        ]);

        $client = Client::query()->find($clientId);
        $prestataire = Prestataire::query()->find($avis->prestataire_id);
        $reporterLabel = $client
            ? trim($client->prenom.' '.$client->nom)
            : 'Client #'.$clientId;
        $prestataireLabel = $prestataire
            ? trim($prestataire->prenom.' '.$prestataire->nom)
            : 'Prestataire #'.$avis->prestataire_id;

        AppMailer::sendAdminAvisSignalement(
            (int) $avis->id,
            (int) $avis->note,
            $avis->commentaire,
            $prestataireLabel,
            $reporterLabel,
            $motif,
        );

        return response()->json([
            'ok' => true,
            'message' => 'Merci. Votre signalement a été transmis à l\'équipe de modération.',
        ]);
    }
}
