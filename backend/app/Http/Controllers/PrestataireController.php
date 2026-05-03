<?php

namespace App\Http\Controllers;

use App\Models\Avis;
use App\Models\Message;
use App\Models\Prestataire;
use App\Models\Service;
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
            $messagesNonLus = (int) Message::query()->where('prestataire_id', $id)->where('lu', 0)->count();
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
        ]);

        $update = [
            'nom' => $data['nom'],
            'prenom' => $data['prenom'],
            'email' => $data['email'],
            'telephone' => $data['telephone'],
            'localite' => $data['localite'],
            'biographie' => $data['biographie'] ?? null,
        ];

        if (Schema::hasColumn('prestataires', 'updated_at')) {
            $update['updated_at'] = now();
        }

        Prestataire::query()->where('id', $id)->update($update);

        $request->session()->put('auth_name', trim($data['prenom'].' '.$data['nom']));

        return response()->json(['ok' => true, 'message' => 'Profil mis à jour.']);
    }

    public function uploadPhoto(Request $request)
    {
        $request->validate([
            'photo' => 'required|file|mimes:jpg,jpeg,png,webp|max:2048',
        ]);

        $id = (int) $request->session()->get('auth_id');
        $file = $request->file('photo');
        $ext = strtolower($file->getClientOriginalExtension() ?: 'jpg');

        $dir = public_path('uploads/prestataires');
        if (! is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        foreach (glob($dir.DIRECTORY_SEPARATOR.$id.'.*') ?: [] as $old) {
            if (is_file($old)) {
                @unlink($old);
            }
        }

        $relative = 'uploads/prestataires/'.$id.'.'.$ext;
        $file->move($dir, $id.'.'.$ext);

        Prestataire::query()->where('id', $id)->update([
            'photo_profil' => $relative,
        ]);

        return response()->json([
            'ok' => true,
            'photo_url' => '/'.$relative,
        ]);
    }
}
