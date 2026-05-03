<?php

namespace App\Http\Controllers;

use App\Models\Avis;
use App\Models\Client;
use App\Models\Prestataire;
use App\Models\Service;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class ClientController extends Controller
{
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
        if (! $p) {
            return response()->json(['ok' => false], 404);
        }

        $services = Service::query()
            ->where('prestataire_id', $id)
            ->orderByDesc('id')
            ->get(['id', 'titre', 'description', 'categorie', 'prix_indicatif', 'delai_execution']);

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
        if (! $p || empty($p->chemin_cv) || ! Storage::disk('local')->exists((string) $p->chemin_cv)) {
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
