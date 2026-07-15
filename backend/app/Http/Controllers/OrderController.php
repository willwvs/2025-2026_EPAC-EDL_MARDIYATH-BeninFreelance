<?php

namespace App\Http\Controllers;

use App\Models\Avis;
use App\Models\Commande;
use App\Models\CommandeLivraison;
use App\Models\Client;
use App\Models\Message;
use App\Models\Prestataire;
use App\Models\Service;
use App\Services\OrderWorkflowService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;

class OrderController extends Controller
{
    public function __construct(
        private readonly OrderWorkflowService $workflow,
    ) {}

    public function indexClient(Request $request)
    {
        return $this->indexForRole($request, 'client');
    }

    /**
     * Compteurs pour les badges nav : messages non-lus + commandes nécessitant une action.
     */
    public function countersClient(Request $request)
    {
        $clientId = (int) $request->session()->get('auth_id');

        $messages = Schema::hasTable('messages')
            ? (int) Message::query()
                ->where('client_id', $clientId)
                ->where('lu', 0)
                ->when(Schema::hasColumn('messages', 'expediteur'), function ($q) {
                    $q->where('expediteur', '!=', 'client');
                })
                ->count()
            : 0;

        $actions = Schema::hasTable('commandes')
            ? (int) Commande::query()
                ->where('client_id', $clientId)
                ->whereIn('statut', ['attente_paiement', 'livree'])
                ->count()
            : 0;

        return response()->json([
            'ok' => true,
            'messages_unread' => $messages,
            'commandes_action' => $actions,
        ]);
    }

    public function countersPrestataire(Request $request)
    {
        $prestataireId = (int) $request->session()->get('auth_id');

        $messages = Schema::hasTable('messages')
            ? (int) Message::query()
                ->where('prestataire_id', $prestataireId)
                ->where('lu', 0)
                ->when(Schema::hasColumn('messages', 'expediteur'), function ($q) {
                    $q->where('expediteur', '!=', 'prestataire');
                })
                ->count()
            : 0;

        $actions = Schema::hasTable('commandes')
            ? (int) Commande::query()
                ->where('prestataire_id', $prestataireId)
                ->whereIn('statut', ['attente_prestataire', 'payee', 'en_revision'])
                ->count()
            : 0;

        return response()->json([
            'ok' => true,
            'messages_unread' => $messages,
            'commandes_action' => $actions,
        ]);
    }

    public function indexPrestataire(Request $request)
    {
        return $this->indexForRole($request, 'prestataire');
    }

    public function showClient(Request $request, int $id)
    {
        return $this->showForRole($request, $id, 'client');
    }

    public function showPrestataire(Request $request, int $id)
    {
        return $this->showForRole($request, $id, 'prestataire');
    }

    public function proposer(Request $request)
    {
        $clientId = (int) $request->session()->get('auth_id');
        $data = $request->validate([
            'prestataire_id' => 'required|integer|min:1',
            'service_id' => 'required|integer|min:1',
            'titre' => 'required|string|max:200',
            'description' => 'required|string|max:8000',
            'montant_fcfa' => 'required|integer|min:500|max:50000000',
            'delai_jours' => 'required|integer|min:1|max:365',
        ]);

        $result = $this->workflow->propose($clientId, $data);
        if (! ($result['ok'] ?? false)) {
            return response()->json(['ok' => false, 'message' => $result['message'] ?? 'Erreur'], 422);
        }

        return response()->json([
            'ok' => true,
            'commande' => $this->serializeCommande($result['commande']),
        ]);
    }

    public function payer(Request $request, int $id)
    {
        $commande = $this->findClientCommande($request, $id);
        if (! $commande) {
            return response()->json(['ok' => false, 'message' => 'Commande introuvable.'], 404);
        }

        $result = $this->workflow->initPayment($commande, (int) $request->session()->get('auth_id'));
        if (! ($result['ok'] ?? false)) {
            return response()->json(['ok' => false, 'message' => $result['message'] ?? 'Erreur'], 422);
        }

        return response()->json([
            'ok' => true,
            'payment_url' => $result['payment_url'],
        ]);
    }

    public function valider(Request $request, int $id)
    {
        $commande = $this->findClientCommande($request, $id);
        if (! $commande) {
            return response()->json(['ok' => false, 'message' => 'Commande introuvable.'], 404);
        }

        $result = $this->workflow->validateDelivery($commande, (int) $request->session()->get('auth_id'));
        if (! ($result['ok'] ?? false)) {
            return response()->json(['ok' => false, 'message' => $result['message'] ?? 'Erreur'], 422);
        }

        return response()->json([
            'ok' => true,
            'commande' => $this->serializeCommande($result['commande']),
            'payout_message' => $result['message'] ?? null,
        ]);
    }

    public function retouche(Request $request, int $id)
    {
        $commande = $this->findClientCommande($request, $id);
        if (! $commande) {
            return response()->json(['ok' => false, 'message' => 'Commande introuvable.'], 404);
        }

        $data = $request->validate([
            'message' => 'nullable|string|max:2000',
        ]);

        $result = $this->workflow->requestRevision($commande, (int) $request->session()->get('auth_id'), $data['message'] ?? null);
        if (! ($result['ok'] ?? false)) {
            return response()->json(['ok' => false, 'message' => $result['message'] ?? 'Erreur'], 422);
        }

        return response()->json(['ok' => true, 'commande' => $this->serializeCommande($result['commande'])]);
    }

    public function annuler(Request $request, int $id)
    {
        $commande = $this->findClientCommande($request, $id);
        if (! $commande) {
            return response()->json(['ok' => false, 'message' => 'Commande introuvable.'], 404);
        }

        $result = $this->workflow->cancelBeforePayment($commande, (int) $request->session()->get('auth_id'));
        if (! ($result['ok'] ?? false)) {
            return response()->json(['ok' => false, 'message' => $result['message'] ?? 'Erreur'], 422);
        }

        return response()->json(['ok' => true, 'commande' => $this->serializeCommande($result['commande'])]);
    }

    public function ouvrirLitige(Request $request, int $id)
    {
        $commande = $this->findClientCommande($request, $id);
        if (! $commande) {
            return response()->json(['ok' => false, 'message' => 'Commande introuvable.'], 404);
        }

        $data = $request->validate([
            'motif' => 'required|string|min:10|max:2000',
        ]);

        $result = $this->workflow->openDispute($commande, (int) $request->session()->get('auth_id'), $data['motif']);
        if (! ($result['ok'] ?? false)) {
            return response()->json(['ok' => false, 'message' => $result['message'] ?? 'Erreur'], 422);
        }

        return response()->json(['ok' => true, 'commande' => $this->serializeCommande($result['commande'])]);
    }

    public function accepter(Request $request, int $id)
    {
        $commande = $this->findPrestataireCommande($request, $id);
        if (! $commande) {
            return response()->json(['ok' => false, 'message' => 'Commande introuvable.'], 404);
        }

        $result = $this->workflow->accept($commande, (int) $request->session()->get('auth_id'));
        if (! ($result['ok'] ?? false)) {
            return response()->json(['ok' => false, 'message' => $result['message'] ?? 'Erreur'], 422);
        }

        return response()->json(['ok' => true, 'commande' => $this->serializeCommande($result['commande'])]);
    }

    public function refuser(Request $request, int $id)
    {
        $commande = $this->findPrestataireCommande($request, $id);
        if (! $commande) {
            return response()->json(['ok' => false, 'message' => 'Commande introuvable.'], 404);
        }

        $data = $request->validate(['motif' => 'nullable|string|max:255']);
        $result = $this->workflow->refuse($commande, (int) $request->session()->get('auth_id'), $data['motif'] ?? null);
        if (! ($result['ok'] ?? false)) {
            return response()->json(['ok' => false, 'message' => $result['message'] ?? 'Erreur'], 422);
        }

        return response()->json(['ok' => true, 'commande' => $this->serializeCommande($result['commande'])]);
    }

    public function livrer(Request $request, int $id)
    {
        $commande = $this->findPrestataireCommande($request, $id);
        if (! $commande) {
            return response()->json(['ok' => false, 'message' => 'Commande introuvable.'], 404);
        }

        $request->validate([
            'message' => 'nullable|string|max:8000',
            'attachments' => 'nullable|array|max:8',
            'attachments.*' => 'file|max:10240|mimes:jpg,jpeg,png,webp,gif,pdf,doc,docx,xls,xlsx,ppt,pptx,csv,txt,zip,rar',
        ]);

        $files = (array) $request->file('attachments', []);
        if ($request->hasFile('attachment') && empty($files)) {
            $files = [$request->file('attachment')];
        }

        $result = $this->workflow->deliver(
            $commande,
            (int) $request->session()->get('auth_id'),
            $request->input('message'),
            $files,
        );

        if (! ($result['ok'] ?? false)) {
            return response()->json(['ok' => false, 'message' => $result['message'] ?? 'Erreur'], 422);
        }

        return response()->json(['ok' => true, 'commande' => $this->serializeCommande($result['commande'])]);
    }

    public function downloadLivraison(Request $request, int $commandeId, int $livraisonId)
    {
        $commande = Commande::query()->find($commandeId);
        if (! $commande) {
            abort(404);
        }

        $role = (string) $request->session()->get('auth_type');
        $authId = (int) $request->session()->get('auth_id');
        $allowed = ($role === 'client' && $commande->client_id === $authId)
            || ($role === 'prestataire' && $commande->prestataire_id === $authId);

        if (! $allowed) {
            abort(403);
        }

        $livraison = CommandeLivraison::query()
            ->where('commande_id', $commandeId)
            ->where('id', $livraisonId)
            ->first();

        if (! $livraison) {
            abort(404);
        }

        $items = $livraison->attachmentList();
        if (empty($items)) {
            abort(404);
        }

        $index = max(0, (int) $request->query('i', 0));
        $target = $items[$index] ?? $items[0];

        if (! \Illuminate\Support\Facades\Storage::disk('local')->exists($target['path'])) {
            abort(404);
        }

        $mime = $target['mime'] ?? null;
        $name = $target['name'] ?: 'livraison';
        $inline = $request->boolean('inline')
            || ($mime && str_starts_with((string) $mime, 'image/'));

        if ($inline) {
            return \Illuminate\Support\Facades\Storage::disk('local')->response(
                $target['path'],
                $name,
                ['Content-Type' => $mime ?: 'application/octet-stream', 'Content-Disposition' => 'inline; filename="'.$name.'"']
            );
        }

        return \Illuminate\Support\Facades\Storage::disk('local')->download($target['path'], $name);
    }

    private function indexForRole(Request $request, string $role): \Illuminate\Http\JsonResponse
    {
        if (! Schema::hasTable('commandes')) {
            return response()->json(['ok' => true, 'commandes' => []]);
        }

        $authId = (int) $request->session()->get('auth_id');
        $q = Commande::query()->orderByDesc('id');
        if ($role === 'client') {
            $q->where('client_id', $authId);
        } else {
            $q->where('prestataire_id', $authId);
        }

        $rows = $q->limit(100)->get()->map(fn ($c) => $this->serializeCommande($c, false, $role));

        return response()->json(['ok' => true, 'commandes' => $rows]);
    }

    private function showForRole(Request $request, int $id, string $role): \Illuminate\Http\JsonResponse
    {
        $commande = $role === 'client'
            ? $this->findClientCommande($request, $id)
            : $this->findPrestataireCommande($request, $id);

        if (! $commande) {
            return response()->json(['ok' => false, 'message' => 'Commande introuvable.'], 404);
        }

        $data = $this->serializeCommande($commande, true, $role);
        $livraisons = CommandeLivraison::query()
            ->where('commande_id', $commande->id)
            ->orderBy('version')
            ->get()
            ->map(function ($l) use ($commande, $role) {
                $base = '/api/'.$role.'/commandes/'.$commande->id.'/livraisons/'.$l->id.'/fichier';
                $items = $l->attachmentList();
                $attachments = [];
                foreach ($items as $i => $a) {
                    $attachments[] = [
                        'name' => $a['name'],
                        'size' => (int) $a['size'],
                        'mime' => $a['mime'],
                        'url' => $base.'?i='.$i,
                        'is_image' => $a['mime'] ? str_starts_with($a['mime'], 'image/') : false,
                    ];
                }

                return [
                    'id' => $l->id,
                    'version' => $l->version,
                    'message' => $l->message,
                    'created_at' => $l->created_at?->toIso8601String(),
                    'attachments' => $attachments,
                    'attachment' => $attachments[0] ?? null,
                ];
            });

        $data['livraisons'] = $livraisons;

        return response()->json(['ok' => true, 'commande' => $data]);
    }

    private function findClientCommande(Request $request, int $id): ?Commande
    {
        return Commande::query()
            ->where('id', $id)
            ->where('client_id', (int) $request->session()->get('auth_id'))
            ->first();
    }

    private function findPrestataireCommande(Request $request, int $id): ?Commande
    {
        return Commande::query()
            ->where('id', $id)
            ->where('prestataire_id', (int) $request->session()->get('auth_id'))
            ->first();
    }

    private function serializeCommande(Commande $c, bool $detail = false, ?string $viewerRole = null): array
    {
        $client = Client::query()->find($c->client_id);
        $prestataire = Prestataire::query()->find($c->prestataire_id);
        $service = Service::query()->find($c->service_id);

        $row = [
            'id' => $c->id,
            'client_id' => $c->client_id,
            'prestataire_id' => $c->prestataire_id,
            'service_id' => $c->service_id,
            'titre' => $c->titre,
            'description' => $detail ? $c->description : \Illuminate\Support\Str::limit($c->description, 120),
            'montant_fcfa' => (int) $c->montant_fcfa,
            'delai_jours' => (int) $c->delai_jours,
            'statut' => $c->statut,
            'commission_fcfa' => (int) $c->commission_fcfa,
            'net_prestataire_fcfa' => (int) $c->net_prestataire_fcfa,
            'peer_label' => $viewerRole === 'client'
                ? trim(($prestataire?->prenom ?? '').' '.($prestataire?->nom ?? ''))
                : ($viewerRole === 'prestataire'
                    ? trim(($client?->prenom ?? '').' '.($client?->nom ?? ''))
                    : ''),
            'service_titre' => $service?->titre ?? '',
            'created_at' => $c->created_at?->toIso8601String(),
            'acceptee_at' => $c->acceptee_at?->toIso8601String(),
            'payee_at' => $c->payee_at?->toIso8601String(),
            'livree_at' => $c->livree_at?->toIso8601String(),
            'review_until' => $c->review_until?->toIso8601String(),
            'terminee_at' => $c->terminee_at?->toIso8601String(),
            'can_pay' => $c->statut === 'attente_paiement',
            'can_validate' => in_array($c->statut, ['livree', 'en_revision'], true),
            'can_deliver' => in_array($c->statut, ['payee', 'en_revision'], true),
            'can_review' => in_array($c->statut, ['paid_out', 'terminee'], true)
                && Schema::hasTable('avis')
                && Schema::hasColumn('avis', 'commande_id')
                && ! Avis::query()->where('commande_id', $c->id)->exists(),
            'can_open_dispute' => in_array($c->statut, ['payee', 'livree', 'en_revision'], true),
            'has_dispute' => $c->statut === 'litige' || $c->litige_at !== null,
            'litige_at' => $c->litige_at?->toIso8601String(),
            'litige_motif' => $c->litige_motif,
            'litige_resolution' => $c->litige_resolution,
            'litige_resolved_at' => $c->litige_resolved_at?->toIso8601String(),
        ];

        if ($client) {
            $row['client_label'] = trim($client->prenom.' '.$client->nom);
        }
        if ($prestataire) {
            $row['prestataire_label'] = trim($prestataire->prenom.' '.$prestataire->nom);
        }

        if (Schema::hasTable('avis') && Schema::hasColumn('avis', 'commande_id')) {
            $avis = Avis::query()->where('commande_id', $c->id)->first();
            if ($avis) {
                $row['avis'] = [
                    'id' => (int) $avis->id,
                    'note' => (int) $avis->note,
                    'commentaire' => $avis->commentaire ? (string) $avis->commentaire : null,
                    'created_at' => $avis->created_at?->toIso8601String(),
                ];
                $row['can_edit_review'] = $viewerRole === 'client'
                    && (int) $avis->client_id === (int) $c->client_id;
            }
        }

        return $row;
    }
}
