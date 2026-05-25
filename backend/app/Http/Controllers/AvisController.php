<?php

namespace App\Http\Controllers;

use App\Models\Avis;
use App\Models\Commande;
use App\Services\OrderWorkflowService;
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
}
