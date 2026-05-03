<?php

namespace App\Http\Controllers;

use App\Models\Avis;
use App\Models\Prestataire;
use App\Models\Service;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class MarketplaceController extends Controller
{
    public function meta()
    {
        $categories = Service::query()
            ->join('prestataires', 'prestataires.id', '=', 'services.prestataire_id')
            ->where('prestataires.statut_verification', 'valide')
            ->whereNotNull('services.categorie')
            ->where('services.categorie', '!=', '')
            ->distinct()
            ->orderBy('services.categorie')
            ->pluck('services.categorie')
            ->values()
            ->all();

        $localites = Prestataire::query()
            ->where('statut_verification', 'valide')
            ->whereNotNull('localite')
            ->where('localite', '!=', '')
            ->distinct()
            ->orderBy('localite')
            ->pluck('localite')
            ->values()
            ->all();

        return response()->json([
            'ok' => true,
            'categories' => $categories,
            'localites' => $localites,
        ]);
    }

    public function searchPrestataires(Request $request)
    {
        $page = max(1, (int) $request->query('page', 1));
        $perPage = 20;

        $query = Prestataire::query()
            ->from('prestataires', 'p')
            ->where('p.statut_verification', 'valide');

        $categorie = trim((string) $request->query('categorie', ''));
        if ($categorie !== '') {
            $query->whereExists(function ($sub) use ($categorie) {
                $sub->select(DB::raw(1))
                    ->from('services as sv')
                    ->whereColumn('sv.prestataire_id', 'p.id')
                    ->where('sv.categorie', $categorie);
            });
        }

        $localite = trim((string) $request->query('localite', ''));
        if ($localite !== '') {
            $query->where('p.localite', $localite);
        }

        $noteMin = (string) $request->query('note_min', '');
        if ($noteMin !== '') {
            $query->where('p.note_moyenne', '>=', (float) $noteMin);
        }

        $q = trim((string) $request->query('q', ''));
        if ($q !== '') {
            $safe = '%'.addcslashes($q, '%_\\').'%';
            $query->where(function ($w) use ($safe) {
                $w->where('p.prenom', 'like', $safe)
                    ->orWhere('p.nom', 'like', $safe)
                    ->orWhere('p.biographie', 'like', $safe)
                    ->orWhereExists(function ($sub) use ($safe) {
                        $sub->select(DB::raw(1))
                            ->from('services as sv2')
                            ->whereColumn('sv2.prestataire_id', 'p.id')
                            ->where(function ($w2) use ($safe) {
                                $w2->where('sv2.titre', 'like', $safe)
                                    ->orWhere('sv2.description', 'like', $safe)
                                    ->orWhere('sv2.categorie', 'like', $safe);
                            });
                    });
            });
        }

        $nbAvisSql = Schema::hasTable('avis')
            ? '(select count(*) from avis where avis.prestataire_id = p.id)'
            : '0';

        $query->select([
            'p.id',
            'p.nom',
            'p.prenom',
            'p.localite',
            'p.photo_profil',
            'p.note_moyenne',
            'p.biographie',
        ])
            ->selectRaw('(select count(*) from services where services.prestataire_id = p.id) as services_count')
            ->selectRaw('(select id from services where services.prestataire_id = p.id order by services.id desc limit 1) as featured_service_id')
            ->selectRaw('(select titre from services where services.prestataire_id = p.id order by services.id desc limit 1) as headline_titre')
            ->selectRaw('(select categorie from services where services.prestataire_id = p.id order by services.id desc limit 1) as headline_categorie')
            ->selectRaw('(select delai_execution from services where services.prestataire_id = p.id order by services.id desc limit 1) as headline_delai')
            ->selectRaw('(select prix_indicatif from services where services.prestataire_id = p.id order by services.id desc limit 1) as headline_prix')
            ->selectRaw("({$nbAvisSql}) as nb_avis");

        $total = (clone $query)->count();
        $rows = $query
            ->orderByDesc('p.note_moyenne')
            ->orderByDesc('p.id')
            ->offset(($page - 1) * $perPage)
            ->limit($perPage)
            ->get()
            ->map(function ($r) {
                $bio = (string) ($r->biographie ?? '');
                $snippet = $bio !== '' ? mb_substr(preg_replace('/\s+/', ' ', $bio), 0, 160) : '';
                if ($bio !== '' && mb_strlen($bio) > 160) {
                    $snippet .= '…';
                }
                if ($snippet === '') {
                    $snippet = 'Profil vérifié — ouvrez la fiche pour voir les services et les détails.';
                }
                $headlineTitre = (string) ($r->headline_titre ?? '');
                $headlineCat = (string) ($r->headline_categorie ?? '');
                $headline = $headlineTitre !== ''
                    ? $headlineTitre.($headlineCat !== '' ? ' · '.$headlineCat : '')
                    : ($headlineCat !== '' ? $headlineCat : 'Prestataire');

                $featuredId = $r->featured_service_id ?? null;

                return [
                    'id' => $r->id,
                    'nom' => $r->nom,
                    'prenom' => $r->prenom,
                    'localite' => $r->localite,
                    'note_moyenne' => (float) $r->note_moyenne,
                    'photo_url' => $r->photo_profil ? '/'.ltrim((string) $r->photo_profil, '/') : null,
                    'headline' => $headline,
                    'snippet' => $snippet,
                    'services_count' => (int) $r->services_count,
                    'featured_service_id' => $featuredId !== null ? (int) $featuredId : null,
                    'headline_categorie' => $headlineCat ?: null,
                    'headline_delai' => $r->headline_delai ?: null,
                    'headline_prix' => $r->headline_prix !== null ? (float) $r->headline_prix : null,
                    'nb_avis' => (int) $r->nb_avis,
                ];
            })
            ->values();

        return response()->json([
            'ok' => true,
            'data' => $rows,
            'page' => $page,
            'per_page' => $perPage,
            'total' => $total,
            'last_page' => max(1, (int) ceil($total / $perPage)),
        ]);
    }

    public function teaser(int $id)
    {
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
            ->limit(3)
            ->get(['id', 'titre', 'categorie', 'prix_indicatif', 'delai_execution', 'description']);

        $servicesOut = $services->map(function ($s) {
            $desc = (string) ($s->description ?? '');
            $excerpt = mb_substr(preg_replace('/\s+/', ' ', $desc), 0, 200);
            if (mb_strlen($desc) > 200) {
                $excerpt .= '…';
            }

            return [
                'id' => $s->id,
                'titre' => $s->titre,
                'categorie' => $s->categorie,
                'prix_indicatif' => $s->prix_indicatif !== null ? (float) $s->prix_indicatif : null,
                'delai_execution' => $s->delai_execution,
                'description_excerpt' => $excerpt,
            ];
        })->values();

        $nbAvis = Schema::hasTable('avis')
            ? (int) Avis::query()->where('prestataire_id', $id)->count()
            : 0;

        $servicesTotal = (int) Service::query()->where('prestataire_id', $id)->count();

        $bioFull = (string) ($p->biographie ?? '');
        $bioPreview = $bioFull !== '' ? mb_substr(preg_replace('/\s+/', ' ', $bioFull), 0, 220) : '';
        if ($bioFull !== '' && mb_strlen($bioFull) > 220) {
            $bioPreview .= '…';
        }

        return response()->json([
            'ok' => true,
            'prestataire' => [
                'id' => $p->id,
                'nom' => $p->nom,
                'prenom' => $p->prenom,
                'localite' => $p->localite,
                'note_moyenne' => (float) $p->note_moyenne,
                'photo_url' => $p->photo_profil ? '/'.ltrim((string) $p->photo_profil, '/') : null,
                'biographie_preview' => $bioPreview,
                'nb_avis' => $nbAvis,
                'services_count' => $servicesTotal,
            ],
            'services' => $servicesOut,
            'profil_complet_url' => '/prestataires/'.$id.'/profil',
        ]);
    }

    public function publicProfile(int $id)
    {
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

        $bio = (string) ($p->biographie ?? '');
        $bioPublic = $bio;
        if ($bio !== '' && mb_strlen($bio) > 400) {
            $bioPublic = mb_substr($bio, 0, 400).'…';
        }

        return response()->json([
            'ok' => true,
            'prestataire' => [
                'id' => $p->id,
                'nom' => $p->nom,
                'prenom' => $p->prenom,
                'localite' => $p->localite,
                'note_moyenne' => (float) $p->note_moyenne,
                'photo_url' => $p->photo_profil ? '/'.ltrim((string) $p->photo_profil, '/') : null,
                'biographie' => $bioPublic,
                'nb_avis' => $nbAvis,
            ],
            'services' => $services,
            'detail_hint' => 'Connectez-vous en tant que client pour afficher email, téléphone et CV.',
        ]);
    }
}
