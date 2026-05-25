<?php

namespace App\Services;

use App\Models\Commande;
use App\Models\Message;
use Illuminate\Support\Facades\Schema;

/**
 * Injecte automatiquement des messages "événement" dans le fil de conversation
 * associé à une commande (livraison, retouche, validation, etc.).
 *
 * Cela évite que la section "Commandes" devienne un dépôt de contenus
 * et garde toute la collaboration au même endroit, comme le workroom Upwork.
 */
class OrderEventNotifier
{
    public const KIND_DELIVERY = 'delivery';
    public const KIND_REVISION = 'revision_request';
    public const KIND_EVENT = 'event';

    /**
     * Insère un message "événement" rattaché à une commande dans la conversation.
     *
     * @param  array<string, mixed>  $meta  Métadonnées sérialisées en JSON (livraisons, motifs, montants…)
     */
    public function post(
        Commande $commande,
        string $expediteur,
        string $kind,
        string $body,
        array $meta = []
    ): void {
        if (! Schema::hasTable('messages')) {
            return;
        }

        $insert = [
            'client_id' => (int) $commande->client_id,
            'prestataire_id' => (int) $commande->prestataire_id,
            'service_id' => (int) $commande->service_id,
            'contenu' => $body,
            'lu' => 0,
        ];

        if (Schema::hasColumn('messages', 'expediteur')) {
            $insert['expediteur'] = $expediteur;
        }
        if (Schema::hasColumn('messages', 'kind')) {
            $insert['kind'] = $kind;
        }
        if (Schema::hasColumn('messages', 'commande_id')) {
            $insert['commande_id'] = (int) $commande->id;
        }
        if (Schema::hasColumn('messages', 'meta')) {
            $insert['meta'] = json_encode(array_merge([
                'commande_id' => (int) $commande->id,
                'commande_titre' => (string) $commande->titre,
                'commande_montant' => (int) $commande->montant_fcfa,
                'commande_statut' => (string) $commande->statut,
            ], $meta), JSON_UNESCAPED_UNICODE);
        }
        if (Schema::hasColumn('messages', 'created_at')) {
            $insert['created_at'] = now();
        }

        Message::query()->insert($insert);
    }
}
