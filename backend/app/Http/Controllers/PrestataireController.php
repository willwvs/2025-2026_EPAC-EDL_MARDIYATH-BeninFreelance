<?php

namespace App\Http\Controllers;

use App\Models\Avis;
use App\Models\Message;
use App\Models\Prestataire;
use App\Models\Service;
use App\Support\ImageStorage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class PrestataireController extends Controller
{
    public function me(Request $request)
    {
        $id = (int) $request->session()->get('auth_id');
        $row = Prestataire::query()->where('id', $id)->first();
        if (! $row) {
            return response()->json(['ok' => false], 404);
        }

        $photoUrl = null;
        if (! empty($row->photo_profil)) {
            $photoUrl = '/'.ltrim((string) $row->photo_profil, '/');
        }

        $nbAvis = 0;
        if (Schema::hasTable('avis')) {
            $nbAvis = (int) Avis::query()->where('prestataire_id', $id)->count();
        }
        $messagesNonLus = 0;
        if (Schema::hasTable('messages')) {
            $mq = Message::query()->where('prestataire_id', $id)->where('lu', 0);
            if (Schema::hasColumn('messages', 'expediteur')) {
                $mq->where('expediteur', 'client');
            }
            $messagesNonLus = (int) $mq->count();
        }
        $servicesActifs = (int) Service::query()->where('prestataire_id', $id)->count();

        $hasCv = ! empty($row->chemin_cv) && Storage::disk('local')->exists((string) $row->chemin_cv);

        return response()->json([
            'ok' => true,
            'nom' => $row->nom,
            'prenom' => $row->prenom,
            'email' => $row->email,
            'telephone' => $row->telephone,
            'localite' => $row->localite,
            'biographie' => $row->biographie,
            'statut_verification' => $row->statut_verification ?? 'en_attente',
            'motif_refus_ia' => $row->motif_refus_ia ?? null,
            'photo_url' => $photoUrl,
            'note_moyenne' => (float) ($row->note_moyenne ?? 0),
            'nb_avis' => $nbAvis,
            'messages_non_lus' => $messagesNonLus,
            'services_actifs' => $servicesActifs,
            'plan' => 'gratuit',
            'services_max' => 2,
            'has_cv' => $hasCv,
            'cv_preview_url' => $hasCv ? '/api/prestataire/cv' : null,
            'mm_phone' => Schema::hasColumn('prestataires', 'mm_phone') ? ($row->mm_phone ?? null) : null,
            'mm_mode' => Schema::hasColumn('prestataires', 'mm_mode') ? ($row->mm_mode ?? null) : null,
        ]);
    }

    /**
     * CV déposé à l'inscription — lecture réservée au prestataire connecté (prévisualisation navigateur).
     */
    public function myCv(Request $request)
    {
        $id = (int) $request->session()->get('auth_id');
        $row = Prestataire::query()->where('id', $id)->first();
        if (! $row || empty($row->chemin_cv) || ! Storage::disk('local')->exists((string) $row->chemin_cv)) {
            abort(404);
        }

        return Storage::disk('local')->response((string) $row->chemin_cv, 'mon-cv.pdf', [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="mon-cv.pdf"',
        ]);
    }

    public function updateProfile(Request $request)
    {
        $id = (int) $request->session()->get('auth_id');

        $data = $request->validate([
            'nom' => 'required|string|max:100',
            'prenom' => 'required|string|max:100',
            'email' => ['required', 'email', 'max:150', Rule::unique('prestataires', 'email')->ignore($id)],
            'telephone' => 'required|string|max:20',
            'localite' => 'required|string|max:100',
            'biographie' => 'nullable|string|max:5000',
            'mm_phone' => 'nullable|string|max:20',
            'mm_mode' => 'nullable|in:mtn-benin,moov-benin',
        ]);

        $update = [
            'nom' => $data['nom'],
            'prenom' => $data['prenom'],
            'email' => $data['email'],
            'telephone' => $data['telephone'],
            'localite' => $data['localite'],
            'biographie' => $data['biographie'] ?? null,
        ];

        if (Schema::hasColumn('prestataires', 'mm_phone')) {
            $update['mm_phone'] = $data['mm_phone'] ?? null;
        }
        if (Schema::hasColumn('prestataires', 'mm_mode')) {
            $update['mm_mode'] = $data['mm_mode'] ?? null;
        }

        if (Schema::hasColumn('prestataires', 'updated_at')) {
            $update['updated_at'] = now();
        }

        Prestataire::query()->where('id', $id)->update($update);

        $request->session()->put('auth_name', trim($data['prenom'].' '.$data['nom']));

        return response()->json(['ok' => true, 'message' => 'Profil mis à jour.']);
    }

    /**
     * Liste détaillée des avis reçus par le prestataire connecté (incluant commentaires).
     * Permet d'afficher la section « Mes avis » sur le tableau de bord.
     */
    public function avisIndex(Request $request)
    {
        if (! Schema::hasTable('avis')) {
            return response()->json(['ok' => true, 'rows' => []]);
        }

        $id = (int) $request->session()->get('auth_id');
        $rows = Avis::query()
            ->from('avis as a')
            ->leftJoin('clients as c', 'c.id', '=', 'a.client_id')
            ->leftJoin('commandes as cmd', 'cmd.id', '=', 'a.commande_id')
            ->where('a.prestataire_id', $id)
            ->orderByDesc('a.id')
            ->limit(200)
            ->get([
                'a.id', 'a.note', 'a.commentaire', 'a.created_at',
                'c.prenom as client_prenom', 'c.nom as client_nom',
                'cmd.titre as commande_titre', 'cmd.id as commande_id',
            ])
            ->map(function ($r) {
                $prenom = trim((string) ($r->client_prenom ?? ''));
                $nom = trim((string) ($r->client_nom ?? ''));
                $auteur = $prenom !== '' ? $prenom : 'Client';
                if ($nom !== '') {
                    $auteur .= ' '.mb_strtoupper(mb_substr($nom, 0, 1)).'.';
                }

                return [
                    'id' => (int) $r->id,
                    'note' => (int) $r->note,
                    'commentaire' => $r->commentaire ? (string) $r->commentaire : null,
                    'auteur' => $auteur,
                    'commande_id' => $r->commande_id ? (int) $r->commande_id : null,
                    'commande_titre' => $r->commande_titre ?: null,
                    'created_at' => $r->created_at,
                ];
            })
            ->values();

        return response()->json([
            'ok' => true,
            'rows' => $rows,
            'note_moyenne' => (float) (Prestataire::query()->where('id', $id)->value('note_moyenne') ?? 0),
        ]);
    }

    public function uploadPhoto(Request $request)
    {
        $request->validate([
            'photo' => 'required|file|max:5120',
        ]);

        $id = (int) $request->session()->get('auth_id');
        $row = Prestataire::query()->where('id', $id)->first();
        if (! $row) {
            return response()->json(['ok' => false], 404);
        }

        try {
            ImageStorage::deletePublicPath($row->photo_profil);
            $stored = ImageStorage::storeImage($request->file('photo'), 'uploads/prestataires');
        } catch (\Throwable $e) {
            return response()->json(['ok' => false, 'message' => $e->getMessage()], 422);
        }

        Prestataire::query()->where('id', $id)->update([
            'photo_profil' => $stored['path'],
        ]);

        return response()->json([
            'ok' => true,
            'photo_url' => $stored['public_url'],
        ]);
    }
}
