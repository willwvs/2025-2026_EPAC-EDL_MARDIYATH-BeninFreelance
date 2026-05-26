<?php

namespace App\Http\Controllers;

use App\Models\Avis;
use App\Models\Category;
use App\Models\Client;
use App\Models\Commande;
use App\Models\CommandeLivraison;
use App\Models\Message;
use App\Models\Paiement;
use App\Models\Payout;
use App\Models\Prestataire;
use App\Models\Service;
use App\Services\OrderWorkflowService;
use App\Support\AccountStatus;
use App\Support\AppMailer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class AdminController extends Controller
{
    public function __construct(
        private readonly OrderWorkflowService $orderWorkflow,
    ) {}

    public function sessionStatus()
    {
        $signaleAvis = 0;
        if (Schema::hasTable('avis') && Schema::hasColumn('avis', 'signale')) {
            $signaleAvis = (int) Avis::query()->where('signale', true)->count();
        }

        return response()->json([
            'ok' => true,
            'authenticated' => (bool) session('admin_ok'),
            'signale_avis_count' => $signaleAvis,
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
                'statut_compte' => $r->statut ?? AccountStatus::ACTIF,
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
                'statut_compte' => $p->statut ?? AccountStatus::ACTIF,
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

    /**
     * Détail litige : commande, livraisons, fil de conversation (pour trancher).
     */
    public function showDispute(int $id)
    {
        if (! Schema::hasTable('commandes')) {
            return response()->json(['ok' => false, 'message' => 'Table commandes absente.'], 404);
        }

        $commande = Commande::query()->find($id);
        if (! $commande || $commande->litige_at === null) {
            return response()->json(['ok' => false, 'message' => 'Litige introuvable.'], 404);
        }

        $client = Client::query()->find($commande->client_id);
        $prestataire = Prestataire::query()->find($commande->prestataire_id);
        $service = Service::query()->find($commande->service_id);

        $livraisons = CommandeLivraison::query()
            ->where('commande_id', $commande->id)
            ->orderBy('version')
            ->get()
            ->map(function ($l) use ($commande) {
                $items = $l->attachmentList();
                $attachments = [];
                foreach ($items as $i => $a) {
                    $attachments[] = [
                        'name' => $a['name'],
                        'size' => (int) $a['size'],
                        'mime' => $a['mime'],
                        'is_image' => $a['mime'] ? str_starts_with((string) $a['mime'], 'image/') : false,
                        'url' => '/admin/api/commandes/'.$commande->id.'/livraisons/'.$l->id.'/fichier?i='.$i,
                        'preview_url' => '/admin/api/commandes/'.$commande->id.'/livraisons/'.$l->id.'/fichier?i='.$i.'&inline=1',
                    ];
                }

                return [
                    'id' => $l->id,
                    'version' => $l->version,
                    'message' => $l->message,
                    'created_at' => $l->created_at?->format('d/m/Y H:i'),
                    'attachments' => $attachments,
                ];
            })
            ->values();

        $messages = [];
        if (Schema::hasTable('messages')) {
            $messages = Message::query()
                ->where('client_id', $commande->client_id)
                ->where('prestataire_id', $commande->prestataire_id)
                ->where('service_id', $commande->service_id)
                ->orderBy('id')
                ->limit(300)
                ->get()
                ->map(function ($m) {
                    $row = [
                        'id' => $m->id,
                        'expediteur' => $m->expediteur,
                        'kind' => $m->kind ?? 'chat',
                        'contenu' => $m->contenu,
                        'created_at' => $m->created_at?->format('d/m/Y H:i'),
                        'meta' => $m->meta,
                    ];
                    if (! empty($m->attachment_path)) {
                        $mime = $m->attachment_mime ? (string) $m->attachment_mime : null;
                        $row['attachment'] = [
                            'name' => $m->attachment_name ?: 'Pièce jointe',
                            'mime' => $mime,
                            'is_image' => $mime && str_starts_with($mime, 'image/'),
                            'url' => '/admin/api/messages/'.$m->id.'/attachment',
                            'preview_url' => '/admin/api/messages/'.$m->id.'/attachment?inline=1',
                        ];
                    }

                    return $row;
                })
                ->values()
                ->all();
        }

        return response()->json([
            'ok' => true,
            'commande' => [
                'id' => $commande->id,
                'titre' => $commande->titre,
                'description' => $commande->description,
                'montant_fcfa' => (int) $commande->montant_fcfa,
                'statut' => $commande->statut,
                'delai_jours' => (int) $commande->delai_jours,
                'commission_fcfa' => (int) $commande->commission_fcfa,
                'net_prestataire_fcfa' => (int) $commande->net_prestataire_fcfa,
                'litige_at' => $commande->litige_at?->format('d/m/Y H:i'),
                'litige_motif' => $commande->litige_motif,
                'litige_resolution' => $commande->litige_resolution,
                'litige_resolved_at' => $commande->litige_resolved_at?->format('d/m/Y H:i'),
                'client_label' => $client ? trim($client->prenom.' '.$client->nom) : 'Client #'.$commande->client_id,
                'prestataire_label' => $prestataire ? trim($prestataire->prenom.' '.$prestataire->nom) : 'Prestataire #'.$commande->prestataire_id,
                'service_titre' => $service?->titre ?? '',
            ],
            'livraisons' => $livraisons,
            'messages' => $messages,
        ]);
    }

    public function downloadLivraison(Request $request, int $commandeId, int $livraisonId)
    {
        $commande = Commande::query()->find($commandeId);
        if (! $commande) {
            abort(404);
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

        if (! Storage::disk('local')->exists($target['path'])) {
            abort(404);
        }

        $mime = $target['mime'] ?? null;
        $name = $target['name'] ?: 'livraison';
        $inline = $request->boolean('inline') || ($mime && str_starts_with((string) $mime, 'image/'));

        if ($inline) {
            return Storage::disk('local')->response($target['path'], $name, [
                'Content-Type' => $mime ?: 'application/octet-stream',
                'Content-Disposition' => 'inline; filename="'.$name.'"',
            ]);
        }

        return Storage::disk('local')->download($target['path'], $name);
    }

    public function downloadMessageAttachment(Request $request, int $id)
    {
        $message = Message::query()->where('id', $id)->first();
        if (! $message || empty($message->attachment_path)) {
            abort(404);
        }

        if (! Storage::disk('local')->exists((string) $message->attachment_path)) {
            abort(404);
        }

        $path = (string) $message->attachment_path;
        $name = $message->attachment_name ?: 'piece-jointe';
        $mime = $message->attachment_mime ? (string) $message->attachment_mime : null;
        $inline = $request->boolean('inline') || ($mime && str_starts_with($mime, 'image/'));

        if ($inline) {
            return Storage::disk('local')->response($path, $name, [
                'Content-Type' => $mime ?: 'application/octet-stream',
                'Content-Disposition' => 'inline; filename="'.$name.'"',
            ]);
        }

        return Storage::disk('local')->download($path, $name);
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

    public function exportClientsCsv()
    {
        $filename = 'clients_'.now()->format('Y-m-d_His').'.csv';

        return response()->streamDownload(function () {
            $out = fopen('php://output', 'w');
            if ($out === false) {
                return;
            }
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, [
                'id', 'prenom', 'nom', 'email', 'telephone', 'localite', 'statut', 'inscrit_le',
            ], ';');

            Client::query()->orderByDesc('id')->chunk(500, function ($clients) use ($out) {
                foreach ($clients as $c) {
                    fputcsv($out, [
                        $c->id,
                        $c->prenom,
                        $c->nom,
                        $c->email,
                        $c->telephone,
                        $c->localite,
                        $c->statut ?? AccountStatus::ACTIF,
                        $c->created_at?->format('Y-m-d H:i:s'),
                    ], ';');
                }
            });
            fclose($out);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    public function exportPrestatairesCsv()
    {
        $filename = 'prestataires_'.now()->format('Y-m-d_His').'.csv';

        return response()->streamDownload(function () {
            $out = fopen('php://output', 'w');
            if ($out === false) {
                return;
            }
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, [
                'id', 'prenom', 'nom', 'email', 'telephone', 'localite',
                'statut_verification', 'statut_compte', 'note_moyenne', 'inscrit_le',
            ], ';');

            Prestataire::query()->orderByDesc('id')->chunk(500, function ($rows) use ($out) {
                foreach ($rows as $p) {
                    fputcsv($out, [
                        $p->id,
                        $p->prenom,
                        $p->nom,
                        $p->email,
                        $p->telephone,
                        $p->localite,
                        $p->statut_verification ?? '',
                        $p->statut ?? AccountStatus::ACTIF,
                        $p->note_moyenne,
                        $p->created_at?->format('Y-m-d H:i:s'),
                    ], ';');
                }
            });
            fclose($out);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    public function listClients()
    {
        $rows = Client::query()
            ->orderByDesc('id')
            ->limit(300)
            ->get()
            ->map(fn ($c) => [
                'id' => $c->id,
                'nom_complet' => trim($c->prenom.' '.$c->nom),
                'email' => $c->email,
                'telephone' => $c->telephone,
                'localite' => $c->localite,
                'statut' => $c->statut ?? AccountStatus::ACTIF,
                'created_at' => $c->created_at?->format('d/m/Y'),
            ]);

        return response()->json(['ok' => true, 'rows' => $rows]);
    }

    public function updateClientStatut(Request $request, int $id)
    {
        return $this->updateAccountStatut($request, Client::class, 'clients', $id);
    }

    public function updatePrestataireStatut(Request $request, int $id)
    {
        return $this->updateAccountStatut($request, Prestataire::class, 'prestataires', $id);
    }

    /**
     * @param  class-string<Client|Prestataire>  $modelClass
     */
    private function updateAccountStatut(Request $request, string $modelClass, string $table, int $id)
    {
        if (! Schema::hasColumn($table, 'statut')) {
            return response()->json(['ok' => false, 'message' => 'Migration comptes non appliquée.'], 503);
        }

        $data = $request->validate([
            'statut' => ['required', Rule::in([AccountStatus::ACTIF, AccountStatus::SUSPENDU, AccountStatus::SUPPRIME])],
        ]);

        $user = $modelClass::query()->find($id);
        if (! $user) {
            return response()->json(['ok' => false, 'message' => 'Compte introuvable.'], 404);
        }

        $user->update(['statut' => $data['statut']]);

        return response()->json(['ok' => true, 'statut' => $data['statut']]);
    }

    public function listAvis(Request $request)
    {
        if (! Schema::hasTable('avis')) {
            return response()->json(['ok' => true, 'rows' => []]);
        }

        $onlySignale = $request->boolean('signale');

        $rows = Avis::query()
            ->with(['client:id,nom,prenom,email', 'prestataire:id,nom,prenom'])
            ->when($onlySignale, fn ($q) => $q->where('signale', true))
            ->orderByDesc('id')
            ->limit(200)
            ->get()
            ->map(function (Avis $a) {
                return [
                    'id' => $a->id,
                    'note' => (int) $a->note,
                    'commentaire' => $a->commentaire,
                    'signale' => (bool) $a->signale,
                    'signale_motif' => $a->signale_motif,
                    'signale_at' => $a->signale_at?->format('d/m/Y H:i'),
                    'created_at' => $a->created_at?->format('d/m/Y H:i'),
                    'client_label' => trim(($a->client->prenom ?? '').' '.($a->client->nom ?? '')),
                    'prestataire_label' => trim(($a->prestataire->prenom ?? '').' '.($a->prestataire->nom ?? '')),
                    'prestataire_id' => $a->prestataire_id,
                ];
            });

        return response()->json(['ok' => true, 'rows' => $rows]);
    }

    public function dismissAvisSignalement(int $id)
    {
        $avis = Avis::query()->find($id);
        if (! $avis) {
            return response()->json(['ok' => false, 'message' => 'Avis introuvable.'], 404);
        }

        $avis->update([
            'signale' => false,
            'signale_motif' => null,
            'signale_at' => null,
        ]);

        return response()->json(['ok' => true]);
    }

    public function deleteAvis(int $id)
    {
        $avis = Avis::query()->find($id);
        if (! $avis) {
            return response()->json(['ok' => false, 'message' => 'Avis introuvable.'], 404);
        }

        $prestataireId = (int) $avis->prestataire_id;
        $avis->delete();
        $this->orderWorkflow->recalculatePrestataireRating($prestataireId);

        return response()->json(['ok' => true]);
    }

    public function listCategories()
    {
        if (! Schema::hasTable('categories')) {
            return response()->json(['ok' => true, 'rows' => []]);
        }

        $rows = Category::query()
            ->orderBy('ordre')
            ->orderBy('nom')
            ->get()
            ->map(fn (Category $c) => [
                'id' => $c->id,
                'nom' => $c->nom,
                'slug' => $c->slug,
                'ordre' => (int) $c->ordre,
                'active' => (bool) $c->active,
            ]);

        return response()->json(['ok' => true, 'rows' => $rows]);
    }

    public function storeCategory(Request $request)
    {
        $data = $request->validate([
            'nom' => 'required|string|max:100',
            'ordre' => 'nullable|integer|min:0|max:9999',
            'active' => 'nullable|boolean',
        ]);

        $cat = Category::create([
            'nom' => trim($data['nom']),
            'slug' => Str::slug($data['nom']),
            'ordre' => (int) ($data['ordre'] ?? 0),
            'active' => $data['active'] ?? true,
        ]);

        return response()->json(['ok' => true, 'category' => ['id' => $cat->id, 'nom' => $cat->nom]], 201);
    }

    public function updateCategory(Request $request, int $id)
    {
        $cat = Category::query()->find($id);
        if (! $cat) {
            return response()->json(['ok' => false, 'message' => 'Catégorie introuvable.'], 404);
        }

        $data = $request->validate([
            'nom' => 'sometimes|required|string|max:100',
            'ordre' => 'nullable|integer|min:0|max:9999',
            'active' => 'nullable|boolean',
        ]);

        $oldNom = $cat->nom;
        if (isset($data['nom'])) {
            $cat->nom = trim($data['nom']);
            $cat->slug = Str::slug($cat->nom);
        }
        if (array_key_exists('ordre', $data)) {
            $cat->ordre = (int) $data['ordre'];
        }
        if (array_key_exists('active', $data)) {
            $cat->active = (bool) $data['active'];
        }
        $cat->save();

        if (isset($data['nom']) && $oldNom !== $cat->nom && Schema::hasTable('services')) {
            Service::query()->where('categorie', $oldNom)->update(['categorie' => $cat->nom]);
        }

        return response()->json(['ok' => true]);
    }

    public function deleteCategory(int $id)
    {
        $cat = Category::query()->find($id);
        if (! $cat) {
            return response()->json(['ok' => false, 'message' => 'Catégorie introuvable.'], 404);
        }

        $inUse = Service::query()->where('categorie', $cat->nom)->exists();
        if ($inUse) {
            return response()->json([
                'ok' => false,
                'message' => 'Catégorie utilisée par des services. Désactivez-la plutôt.',
            ], 422);
        }

        $cat->delete();

        return response()->json(['ok' => true]);
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
