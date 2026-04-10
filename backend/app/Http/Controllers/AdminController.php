<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class AdminController extends Controller
{
    public function sessionStatus()
    {
        return response()->json([
            'ok' => true,
            'authenticated' => (bool) session('admin_ok'),
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
        $rows = DB::table('prestataires')
            ->orderByDesc('id')
            ->limit(200)
            ->get()
            ->map(fn ($r) => [
                'id' => $r->id,
                'nom_complet' => trim($r->prenom . ' ' . $r->nom),
                'email' => $r->email,
                'statut' => $r->statut_verification ?? '',
            ]);

        return response()->json(['ok' => true, 'rows' => $rows]);
    }

    public function showApi(int $id)
    {
        $p = DB::table('prestataires')->where('id', $id)->first();
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
                'motif_refus_ia' => $p->motif_refus_ia,
            ],
            'urls' => [
                'cv' => url('/admin/prestataires/' . $id . '/fichiers/cv'),
                'identite' => url('/admin/prestataires/' . $id . '/fichiers/identite'),
            ],
        ]);
    }

    public function validateApi(int $id)
    {
        DB::table('prestataires')->where('id', $id)->update([
            'statut_verification' => 'valide',
            'motif_refus_ia' => null,
        ]);

        return response()->json(['ok' => true]);
    }

    public function refuseApi(Request $request, int $id)
    {
        $motif = (string) $request->input('motif', 'Dossier refuse par l\'administrateur.');
        DB::table('prestataires')->where('id', $id)->update([
            'statut_verification' => 'refuse',
            'motif_refus_ia' => $motif,
        ]);

        return response()->json(['ok' => true]);
    }

    public function file(int $id, string $type)
    {
        $p = DB::table('prestataires')->where('id', $id)->first();
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
