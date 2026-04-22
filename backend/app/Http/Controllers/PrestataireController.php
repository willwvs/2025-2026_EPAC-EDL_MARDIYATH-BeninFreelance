<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;

class PrestataireController extends Controller
{
    public function me(Request $request)
    {
        $id = (int) $request->session()->get('auth_id');
        $row = DB::table('prestataires')->where('id', $id)->first();
        if (! $row) {
            return response()->json(['ok' => false], 404);
        }

        $photoUrl = null;
        if (! empty($row->photo_profil)) {
            $photoUrl = '/'.ltrim((string) $row->photo_profil, '/');
        }

        return response()->json([
            'ok' => true,
            'nom' => $row->nom,
            'prenom' => $row->prenom,
            'email' => $row->email,
            'telephone' => $row->telephone,
            'localite' => $row->localite,
            'biographie' => $row->biographie,
            'statut_verification' => $row->statut_verification ?? 'en_attente',
            'photo_url' => $photoUrl,
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

        DB::table('prestataires')->where('id', $id)->update($update);

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

        DB::table('prestataires')->where('id', $id)->update([
            'photo_profil' => $relative,
        ]);

        return response()->json([
            'ok' => true,
            'photo_url' => '/'.$relative,
        ]);
    }
}
