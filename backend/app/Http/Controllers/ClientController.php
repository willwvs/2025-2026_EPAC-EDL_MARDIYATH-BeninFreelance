<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class ClientController extends Controller
{
    public function me(Request $request)
    {
        $id = (int) $request->session()->get('auth_id');
        $row = DB::table('clients')->where('id', $id)->first();
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

        DB::table('clients')->where('id', $id)->update([
            'nom' => $data['nom'],
            'prenom' => $data['prenom'],
            'email' => $data['email'],
            'telephone' => $data['telephone'],
            'localite' => $data['localite'],
        ]);

        $request->session()->put('auth_name', trim($data['prenom'].' '.$data['nom']));

        return response()->json(['ok' => true, 'message' => 'Profil mis à jour.']);
    }
}
