<?php

namespace App\Http\Controllers;

use App\Models\Avis;
use App\Models\Client;
use App\Models\Prestataire;
use App\Models\Service;
use App\Services\SubscriptionService;
use App\Support\ImageStorage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class ClientController extends Controller
{
    public function __construct(
        private readonly SubscriptionService $subscriptions,
    ) {}
    public function me(Request $request)
    {
        $id = (int) $request->session()->get('auth_id');
        $row = Client::query()->where('id', $id)->first();
        if (! $row) {
            return response()->json(['ok' => false], 404);
        }

        return response()->json([
            'ok' => true,
            'nom' => $row->nom,
            'prenom' => $row->prenom,
            'email' => $row->email,
            'telephone' => $row->telephone,
            'localite' => $row->localite,
        ]);
    }

    public function updateProfile(Request $request)
    {
        $id = (int) $request->session()->get('auth_id');

        $data = $request->validate([
            'nom' => 'required|string|max:100',
            'prenom' => 'required|string|max:100',
            'email' => ['required', 'email', 'max:150', Rule::unique('clients', 'email')->ignore($id)],
            'telephone' => 'required|string|max:20',
            'localite' => 'required|string|max:100',
        ]);

        Client::query()->where('id', $id)->update([
            'nom' => $data['nom'],
            'prenom' => $data['prenom'],
            'email' => $data['email'],
            'telephone' => $data['telephone'],
            'localite' => $data['localite'],
        ]);

        $request->session()->put('auth_name', trim($data['prenom'].' '.$data['nom']));

        return response()->json(['ok' => true, 'message' => 'Profil mis à jour.']);
    }

    /**
     * Fiche prestataire complète (client connecté) : coordonnées, bio intégrale, lien CV.
     */
    public function prestataireProfil(Request $request, int $id)
    {
        if ($request->session()->get('auth_type') !== 'client') {
            return response()->json(['ok' => false, 'reason' => 'auth'], 401);
        }

        $p = Prestataire::query()
            ->where('id', $id)
            ->where('statut_verification', 'valide')
            ->first();
        if (! $p || ! $this->subscriptions->isDiscoverable($p)) {
            return response()->json(['ok' => false], 404);
        }

        $serviceCols = ['id', 'titre', 'description', 'categorie', 'prix_indicatif', 'delai_execution'];
        if (Schema::hasColumn('services', 'photo_path')) {
            $serviceCols[] = 'photo_path';
        }

        $services = Service::query()
            ->where('prestataire_id', $id)
            ->orderByDesc('id')
            ->get($serviceCols)
            ->map(function ($s) {
                return [
                    'id' => $s->id,
                    'titre' => $s->titre,
                    'description' => $s->description,
                    'categorie' => $s->categorie,
                    'prix_indicatif' => $s->prix_indicatif !== null ? (float) $s->prix_indicatif : null,
                    'delai_execution' => $s->delai_execution,
                    'photo_url' => Schema::hasColumn('services', 'photo_path')
                        ? ImageStorage::publicUrl($s->photo_path ?? null)
                        : null,
                    'detail_url' => '/service/'.$s->id,
                ];
            })
            ->values();

        $nbAvis = Schema::hasTable('avis')
            ? (int) Avis::query()->where('prestataire_id', $id)->count()
            : 0;

        $hasCv = ! empty($p->chemin_cv) && Storage::disk('local')->exists((string) $p->chemin_cv);

        return response()->json([
            'ok' => true,
            'prestataire' => [
                'id' => $p->id,
                'nom' => $p->nom,
                'prenom' => $p->prenom,
                'localite' => $p->localite,
                'email' => $p->email,
                'telephone' => $p->telephone,
                'note_moyenne' => (float) $p->note_moyenne,
                'photo_url' => $p->photo_profil ? '/'.ltrim((string) $p->photo_profil, '/') : null,
                'biographie' => $p->biographie,
                'nb_avis' => $nbAvis,
                'has_cv' => $hasCv,
                'cv_preview_url' => $hasCv ? '/api/client/prestataires/'.$id.'/cv?inline=1' : null,
                'cv_download_url' => $hasCv ? '/api/client/prestataires/'.$id.'/cv' : null,
            ],
            'services' => $services,
        ]);
    }

    /**
     * Liste des avis publiés par le client connecté (sur ses commandes terminées).
     */
    public function avisIndex(Request $request)
    {
        if (! Schema::hasTable('avis')) {
            return response()->json(['ok' => true, 'rows' => []]);
        }

        $clientId = (int) $request->session()->get('auth_id');
        $rows = Avis::query()
            ->from('avis as a')
            ->leftJoin('prestataires as p', 'p.id', '=', 'a.prestataire_id')
            ->leftJoin('commandes as cmd', 'cmd.id', '=', 'a.commande_id')
            ->where('a.client_id', $clientId)
            ->orderByDesc('a.id')
            ->limit(200)
            ->get([
                'a.id', 'a.note', 'a.commentaire', 'a.created_at',
                'p.prenom as p_prenom', 'p.nom as p_nom', 'p.id as prestataire_id',
                'cmd.id as commande_id', 'cmd.titre as commande_titre',
            ])
            ->map(function ($r) {
                return [
                    'id' => (int) $r->id,
                    'note' => (int) $r->note,
                    'commentaire' => $r->commentaire ? (string) $r->commentaire : null,
                    'prestataire_id' => $r->prestataire_id ? (int) $r->prestataire_id : null,
                    'prestataire_label' => trim(($r->p_prenom ?? '').' '.($r->p_nom ?? '')) ?: 'Prestataire',
                    'commande_id' => $r->commande_id ? (int) $r->commande_id : null,
                    'commande_titre' => $r->commande_titre ?: null,
                    'created_at' => $r->created_at,
                ];
            })
            ->values();

        return response()->json(['ok' => true, 'rows' => $rows]);
    }

    /**
     * Téléchargement du CV (dépôt d’inscription) — réservé aux clients connectés.
     */
    public function prestataireCv(Request $request, int $id)
    {
        if ($request->session()->get('auth_type') !== 'client') {
            abort(401);
        }

        $p = Prestataire::query()
            ->where('id', $id)
            ->where('statut_verification', 'valide')
            ->first();
        if (! $p || ! $this->subscriptions->isDiscoverable($p) || empty($p->chemin_cv) || ! Storage::disk('local')->exists((string) $p->chemin_cv)) {
            abort(404);
        }

        $inline = $request->boolean('inline');
        if ($inline) {
            return Storage::disk('local')->response((string) $p->chemin_cv, 'cv-'.$id.'.pdf', [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => 'inline; filename="cv-'.$id.'.pdf"',
            ]);
        }

        return Storage::disk('local')->response((string) $p->chemin_cv, 'cv-'.$id.'.pdf', [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="cv-'.$id.'.pdf"',
        ]);
    }
}
