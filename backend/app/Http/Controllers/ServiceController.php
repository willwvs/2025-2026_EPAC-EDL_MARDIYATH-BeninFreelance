<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ServiceController extends Controller
{
    public function indexMine(Request $request)
    {
        $prestataireId = (int) $request->session()->get('auth_id');
        $rows = DB::table('services')
            ->where('prestataire_id', $prestataireId)
            ->orderByDesc('id')
            ->get();

        return response()->json(['ok' => true, 'rows' => $rows]);
    }

    public function store(Request $request)
    {
        $prestataireId = (int) $request->session()->get('auth_id');
        $data = $request->validate([
            'titre' => 'required|string|max:200',
            'description' => 'required|string|max:10000',
            'categorie' => 'required|string|max:100',
            'prix_indicatif' => 'nullable|numeric|min:0',
            'delai_execution' => 'nullable|string|max:100',
        ]);

        $existing = (int) DB::table('services')->where('prestataire_id', $prestataireId)->count();
        if ($existing >= 2) {
            return response()->json([
                'ok' => false,
                'message' => 'Limite du plan gratuit (2 services) atteinte. Supprimez un service pour en ajouter un autre.',
            ], 422);
        }

        $id = DB::table('services')->insertGetId([
            'prestataire_id' => $prestataireId,
            'titre' => $data['titre'],
            'description' => $data['description'],
            'categorie' => $data['categorie'],
            'prix_indicatif' => $data['prix_indicatif'] ?? null,
            'delai_execution' => $data['delai_execution'] ?? null,
            'created_at' => now(),
        ]);

        return response()->json(['ok' => true, 'id' => $id], 201);
    }

    public function update(Request $request, int $id)
    {
        $prestataireId = (int) $request->session()->get('auth_id');
        $owns = DB::table('services')
            ->where('id', $id)
            ->where('prestataire_id', $prestataireId)
            ->exists();
        if (! $owns) {
            return response()->json(['ok' => false, 'message' => 'Service introuvable.'], 404);
        }

        $data = $request->validate([
            'titre' => 'required|string|max:200',
            'description' => 'required|string|max:10000',
            'categorie' => 'required|string|max:100',
            'prix_indicatif' => 'nullable|numeric|min:0',
            'delai_execution' => 'nullable|string|max:100',
        ]);

        DB::table('services')
            ->where('id', $id)
            ->where('prestataire_id', $prestataireId)
            ->update([
                'titre' => $data['titre'],
                'description' => $data['description'],
                'categorie' => $data['categorie'],
                'prix_indicatif' => $data['prix_indicatif'] ?? null,
                'delai_execution' => $data['delai_execution'] ?? null,
            ]);

        return response()->json(['ok' => true]);
    }

    public function destroy(Request $request, int $id)
    {
        $prestataireId = (int) $request->session()->get('auth_id');
        $deleted = DB::table('services')
            ->where('id', $id)
            ->where('prestataire_id', $prestataireId)
            ->delete();
        if ($deleted < 1) {
            return response()->json(['ok' => false, 'message' => 'Service introuvable.'], 404);
        }

        return response()->json(['ok' => true]);
    }
}
