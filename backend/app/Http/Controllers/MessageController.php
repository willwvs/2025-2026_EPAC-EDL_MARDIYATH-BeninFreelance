<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class MessageController extends Controller
{
    public function contactContext(Request $request)
    {
        $prestataireId = (int) $request->query('prestataire_id', 0);
        $serviceId = (int) $request->query('service_id', 0);
        if ($prestataireId < 1 || $serviceId < 1) {
            return response()->json(['ok' => false, 'message' => 'Paramètres invalides.'], 422);
        }

        $p = DB::table('prestataires')
            ->where('id', $prestataireId)
            ->where('statut_verification', 'valide')
            ->first();
        if (! $p) {
            return response()->json(['ok' => false, 'message' => 'Prestataire introuvable.'], 404);
        }

        $svc = DB::table('services')
            ->where('id', $serviceId)
            ->where('prestataire_id', $prestataireId)
            ->first();
        if (! $svc) {
            return response()->json(['ok' => false, 'message' => 'Service introuvable pour ce prestataire.'], 404);
        }

        return response()->json([
            'ok' => true,
            'prestataire' => [
                'id' => $p->id,
                'nom' => $p->nom,
                'prenom' => $p->prenom,
                'localite' => $p->localite,
            ],
            'service' => [
                'id' => $svc->id,
                'titre' => $svc->titre,
                'categorie' => $svc->categorie,
                'delai_execution' => $svc->delai_execution,
                'prix_indicatif' => $svc->prix_indicatif !== null ? (float) $svc->prix_indicatif : null,
            ],
            'can_reply' => false,
        ]);
    }

    public function storeFirstContact(Request $request)
    {
        if (! Schema::hasTable('messages')) {
            return response()->json(['ok' => false, 'message' => 'Messagerie non disponible.'], 503);
        }

        $clientId = (int) $request->session()->get('auth_id');
        $data = $request->validate([
            'prestataire_id' => 'required|integer|min:1',
            'service_id' => 'required|integer|min:1',
            'objet' => 'nullable|string|max:200',
            'contenu' => 'required|string|min:10|max:8000',
        ]);

        $prestataireId = (int) $data['prestataire_id'];
        $serviceId = (int) $data['service_id'];

        $p = DB::table('prestataires')
            ->where('id', $prestataireId)
            ->where('statut_verification', 'valide')
            ->first();
        if (! $p) {
            return response()->json(['ok' => false, 'message' => 'Prestataire introuvable.'], 404);
        }

        $svc = DB::table('services')
            ->where('id', $serviceId)
            ->where('prestataire_id', $prestataireId)
            ->first();
        if (! $svc) {
            return response()->json(['ok' => false, 'message' => 'Service invalide.'], 422);
        }

        $exists = DB::table('messages')
            ->where('client_id', $clientId)
            ->where('service_id', $serviceId)
            ->exists();
        if ($exists) {
            return response()->json([
                'ok' => false,
                'message' => 'Vous avez déjà envoyé un premier message pour ce service.',
            ], 409);
        }

        $client = DB::table('clients')->where('id', $clientId)->first();
        if (! $client) {
            return response()->json(['ok' => false], 404);
        }

        $objet = isset($data['objet']) ? trim((string) $data['objet']) : '';
        $body = trim($data['contenu']);
        if ($objet !== '') {
            $body = 'Objet : '.$objet."\n\n".$body;
        }
        $tel = trim((string) ($client->telephone ?? ''));
        $snapshot = "\n\n—\nEnvoyé par : ".trim($client->prenom.' '.$client->nom);
        $snapshot .= ' <'.$client->email.'>';
        if ($tel !== '') {
            $snapshot .= ' · '.$tel;
        }
        $full = $body.$snapshot;

        $insert = [
            'client_id' => $clientId,
            'prestataire_id' => $prestataireId,
            'service_id' => $serviceId,
            'contenu' => $full,
            'lu' => 0,
        ];
        if (Schema::hasColumn('messages', 'created_at')) {
            $insert['created_at'] = now();
        }

        DB::table('messages')->insert($insert);

        return response()->json([
            'ok' => true,
            'message' => 'Votre message a été envoyé.',
            'can_reply' => false,
        ]);
    }

    public function indexClient(Request $request)
    {
        if (! Schema::hasTable('messages')) {
            return response()->json(['ok' => true, 'data' => [], 'can_reply' => false]);
        }

        $clientId = (int) $request->session()->get('auth_id');
        $rows = DB::table('messages as m')
            ->join('prestataires as p', 'p.id', '=', 'm.prestataire_id')
            ->join('services as s', 's.id', '=', 'm.service_id')
            ->where('m.client_id', $clientId)
            ->orderByDesc('m.id')
            ->select([
                'm.id',
                'm.contenu',
                'm.created_at',
                'p.nom as prestataire_nom',
                'p.prenom as prestataire_prenom',
                's.titre as service_titre',
            ])
            ->get()
            ->map(function ($r) {
                return [
                    'id' => $r->id,
                    'prestataire_label' => trim($r->prestataire_prenom.' '.$r->prestataire_nom),
                    'service_titre' => $r->service_titre,
                    'contenu_excerpt' => $this->excerptForList((string) $r->contenu),
                    'created_at' => $r->created_at,
                ];
            })
            ->values();

        return response()->json([
            'ok' => true,
            'data' => $rows,
            'can_reply' => false,
        ]);
    }

    public function indexPrestataire(Request $request)
    {
        if (! Schema::hasTable('messages')) {
            return response()->json(['ok' => true, 'data' => [], 'can_reply' => false]);
        }

        $prestataireId = (int) $request->session()->get('auth_id');
        $rows = DB::table('messages as m')
            ->join('clients as c', 'c.id', '=', 'm.client_id')
            ->join('services as s', 's.id', '=', 'm.service_id')
            ->where('m.prestataire_id', $prestataireId)
            ->orderByDesc('m.id')
            ->select([
                'm.id',
                'm.contenu',
                'm.lu',
                'm.created_at',
                'c.nom as client_nom',
                'c.prenom as client_prenom',
                's.titre as service_titre',
            ])
            ->get()
            ->map(function ($r) {
                return [
                    'id' => $r->id,
                    'client_label' => trim($r->client_prenom.' '.$r->client_nom),
                    'service_titre' => $r->service_titre,
                    'contenu_excerpt' => $this->excerptForList((string) $r->contenu),
                    'lu' => (bool) $r->lu,
                    'created_at' => $r->created_at,
                ];
            })
            ->values();

        return response()->json([
            'ok' => true,
            'data' => $rows,
            'can_reply' => false,
        ]);
    }

    private function excerptForList(string $contenu): string
    {
        $cut = $contenu;
        $pos = mb_strpos($contenu, "\n\n—\n");
        if ($pos !== false && $pos > 0) {
            $cut = mb_substr($contenu, 0, $pos);
        }
        $cut = trim(preg_replace('/\s+/', ' ', $cut));
        if (mb_strlen($cut) > 320) {
            return mb_substr($cut, 0, 320).'…';
        }

        return $cut;
    }
}
