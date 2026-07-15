<?php

namespace App\Http\Controllers;

use App\Models\Avis;
use App\Models\Category;
use App\Models\Prestataire;
use App\Models\Service;
use App\Services\SubscriptionService;
use App\Support\ImageStorage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class MarketplaceController extends Controller
{
    public function __construct(
        private readonly SubscriptionService $subscriptions,
    ) {}

    public function meta()
    {
        $categories = Category::activeNames();
        if ($categories === []) {
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
        }

        $localites = Prestataire::query()
            ->where('statut_verification', 'valide')
            ->tap(fn ($q) => $this->scopeActiveAccount($q))
            ->whereNotNull('localite')
            ->where('localite', '!=', '')
            ->distinct()
            ->orderBy('localite')
            ->pluck('localite')
            ->values()
            ->all();

        $localites = collect(config('marketplace.localites_suggestees', []))
            ->merge($localites)
            ->map(fn ($v) => trim((string) $v))
            ->filter(fn ($v) => $v !== '')
            ->unique()
            ->sort()
            ->values()
            ->all();

        return response()->json([
            'ok' => true,
            'categories' => $categories,
            'localites' => $localites,
        ]);
    }

    public function searchServices(Request $request)
    {
        $page = max(1, (int) $request->query('page', 1));
        $perPage = 20;

        $query = Service::query()
            ->from('services as s')
            ->join('prestataires as p', 'p.id', '=', 's.prestataire_id')
            ->where('p.statut_verification', 'valide');
        $this->scopeActiveAccount($query, 'p');
        $this->scopeDiscoverableSubscription($query, 'p');

        $categorie = trim((string) $request->query('categorie', ''));
        if ($categorie !== '') {
            $query->where('s.categorie', $categorie);
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
                $w->where('s.titre', 'like', $safe)
                    ->orWhere('s.description', 'like', $safe)
                    ->orWhere('s.categorie', 'like', $safe)
                    ->orWhere('p.prenom', 'like', $safe)
                    ->orWhere('p.nom', 'like', $safe);
            });
        }

        $nbAvisSql = Schema::hasTable('avis')
            ? '(select count(*) from avis where avis.prestataire_id = p.id)'
            : '0';

        $hasPhoto = Schema::hasColumn('services', 'photo_path');

        $query->select([
            's.id',
            's.titre',
            's.categorie',
            's.prix_indicatif',
            's.delai_execution',
            's.description',
            'p.id as prestataire_id',
            'p.nom',
            'p.prenom',
            'p.localite',
            'p.photo_profil',
            'p.note_moyenne',
        ])
            ->selectRaw("({$nbAvisSql}) as nb_avis");

        if ($hasPhoto) {
            $query->addSelect('s.photo_path');
        }

        $total = (clone $query)->count('s.id');
        $rows = $query
            ->selectRaw($this->planPrioritySql('p').' as plan_priority')
            ->orderByDesc('plan_priority')
            ->orderByDesc('p.note_moyenne')
            ->orderByDesc('s.id')
            ->offset(($page - 1) * $perPage)
            ->limit($perPage)
            ->get()
            ->map(function ($r) use ($hasPhoto) {
                $desc = (string) ($r->description ?? '');
                $snippet = mb_substr(preg_replace('/\s+/', ' ', $desc), 0, 160);
                if (mb_strlen($desc) > 160) {
                    $snippet .= '…';
                }

                return [
                    'id' => (int) $r->id,
                    'titre' => $r->titre,
                    'categorie' => $r->categorie,
                    'prix_indicatif' => $r->prix_indicatif !== null ? (float) $r->prix_indicatif : null,
                    'delai_execution' => $r->delai_execution,
                    'snippet' => $snippet,
                    'photo_url' => $hasPhoto ? ImageStorage::publicUrl($r->photo_path ?? null) : null,
                    'prestataire' => [
                        'id' => (int) $r->prestataire_id,
                        'nom' => $r->nom,
                        'prenom' => $r->prenom,
                        'localite' => $r->localite,
                        'note_moyenne' => (float) $r->note_moyenne,
                        'photo_url' => $r->photo_profil ? '/'.ltrim((string) $r->photo_profil, '/') : null,
                        'nb_avis' => (int) $r->nb_avis,
                    ],
                    'detail_url' => '/service/'.$r->id,
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

    public function showService(int $id)
    {
        $hasPhoto = Schema::hasColumn('services', 'photo_path');

        $query = Service::query()
            ->from('services as s')
            ->join('prestataires as p', 'p.id', '=', 's.prestataire_id')
            ->where('s.id', $id)
            ->where('p.statut_verification', 'valide');
        $this->scopeActiveAccount($query, 'p');
        $this->scopeDiscoverableSubscription($query, 'p');

        $query->select([
            's.id',
            's.titre',
            's.description',
            's.categorie',
            's.prix_indicatif',
            's.delai_execution',
            's.prestataire_id',
            'p.nom',
            'p.prenom',
            'p.localite',
            'p.photo_profil',
            'p.note_moyenne',
            'p.biographie',
        ]);

        if ($hasPhoto) {
            $query->addSelect('s.photo_path');
        }

        $row = $query->first();
        if (! $row) {
            return response()->json(['ok' => false], 404);
        }

        $nbAvis = Schema::hasTable('avis')
            ? (int) Avis::query()->where('prestataire_id', $row->prestataire_id)->count()
            : 0;

        $pid = (int) $row->prestataire_id;
        $sid = (int) $row->id;

        return response()->json([
            'ok' => true,
            'service' => [
                'id' => $sid,
                'titre' => $row->titre,
                'description' => $row->description,
                'categorie' => $row->categorie,
                'prix_indicatif' => $row->prix_indicatif !== null ? (float) $row->prix_indicatif : null,
                'delai_execution' => $row->delai_execution,
                'photo_url' => $hasPhoto ? ImageStorage::publicUrl($row->photo_path ?? null) : null,
            ],
            'prestataire' => [
                'id' => $pid,
                'nom' => $row->nom,
                'prenom' => $row->prenom,
                'localite' => $row->localite,
                'note_moyenne' => (float) $row->note_moyenne,
                'photo_url' => $row->photo_profil ? '/'.ltrim((string) $row->photo_profil, '/') : null,
                'nb_avis' => $nbAvis,
                'biographie_excerpt' => $this->bioExcerpt((string) ($row->biographie ?? ''), 200),
                'profil_url' => '/prestataires/'.$pid,
                'teaser_url' => '/prestataires/'.$pid,
            ],
            'contact_url' => '/contact/prestataire/'.$pid.'?service='.$sid,
            'login_url' => '/login',
        ]);
    }

    public function searchPrestataires(Request $request)
    {
        $page = max(1, (int) $request->query('page', 1));
        $perPage = 20;

        $query = Prestataire::query()
            ->from('prestataires', 'p')
            ->where('p.statut_verification', 'valide');
        $this->scopeActiveAccount($query, 'p');
        $this->scopeDiscoverableSubscription($query, 'p');

        if ($request->boolean('only_with_services')) {
            $query->whereExists(function ($sub) {
                $sub->select(DB::raw(1))
                    ->from('services as sv0')
                    ->whereColumn('sv0.prestataire_id', 'p.id');
            });
        }

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
            ->selectRaw($this->planPrioritySql('p').' as plan_priority')
            ->orderByDesc('plan_priority')
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
        if (! $p || ! $this->prestataireIsPubliclyVisible($p)) {
            return response()->json(['ok' => false], 404);
        }

        $serviceCols = ['id', 'titre', 'categorie', 'prix_indicatif', 'delai_execution', 'description'];
        if (Schema::hasColumn('services', 'photo_path')) {
            $serviceCols[] = 'photo_path';
        }

        $services = Service::query()
            ->where('prestataire_id', $id)
            ->orderByDesc('id')
            ->limit(3)
            ->get($serviceCols);

        $servicesOut = $services->map(fn ($s) => $this->publicServiceCard($s))->values();

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
        if (! $p || ! $this->prestataireIsPubliclyVisible($p)) {
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
            ->map(fn ($s) => $this->publicServiceCard($s, true))
            ->values();

        $nbAvis = Schema::hasTable('avis')
            ? (int) Avis::query()->where('prestataire_id', $id)->count()
            : 0;

        $bio = (string) ($p->biographie ?? '');

        return response()->json([
            'ok' => true,
            'prestataire' => [
                'id' => $p->id,
                'nom' => $p->nom,
                'prenom' => $p->prenom,
                'localite' => $p->localite,
                'note_moyenne' => (float) $p->note_moyenne,
                'photo_url' => $p->photo_profil ? '/'.ltrim((string) $p->photo_profil, '/') : null,
                'biographie' => $this->bioExcerpt($bio, 400),
                'nb_avis' => $nbAvis,
            ],
            'services' => $services,
            'detail_hint' => 'Connectez-vous en tant que client pour afficher email, téléphone et CV.',
        ]);
    }

    /**
     * Liste publique des avis reçus par un prestataire (note + commentaire + auteur abrégé).
     * Anonymise partiellement le client (prénom + initiale du nom) pour rester pudique.
     */
    public function publicAvis(Request $request, int $id)
    {
        if (! Schema::hasTable('avis')) {
            return response()->json(['ok' => true, 'avis' => [], 'stats' => $this->emptyAvisStats()]);
        }

        $page = max(1, (int) $request->query('page', 1));
        $perPage = 10;

        $base = Avis::query()
            ->from('avis as a')
            ->leftJoin('clients as c', 'c.id', '=', 'a.client_id')
            ->leftJoin('commandes as cmd', 'cmd.id', '=', 'a.commande_id')
            ->where('a.prestataire_id', $id);

        $authClientId = $request->session()->get('auth_type') === 'client'
            ? (int) $request->session()->get('auth_id')
            : 0;
        $hasSignale = Schema::hasColumn('avis', 'signale');

        $select = [
            'a.id',
            'a.note',
            'a.commentaire',
            'a.created_at',
            'a.client_id',
            'c.prenom as client_prenom',
            'c.nom as client_nom',
            'cmd.titre as commande_titre',
        ];
        if ($hasSignale) {
            $select[] = 'a.signale';
        }

        $total = (clone $base)->count();
        $rows = $base
            ->orderByDesc('a.id')
            ->forPage($page, $perPage)
            ->get($select);

        $avis = $rows->map(function ($r) use ($authClientId, $hasSignale) {
            $prenom = trim((string) ($r->client_prenom ?? ''));
            $nom = trim((string) ($r->client_nom ?? ''));
            $auteur = $prenom !== '' ? $prenom : 'Client';
            if ($nom !== '') {
                $auteur .= ' '.mb_strtoupper(mb_substr($nom, 0, 1)).'.';
            }

            $own = $authClientId > 0 && (int) $r->client_id === $authClientId;
            $signale = $hasSignale && (bool) ($r->signale ?? false);

            return [
                'id' => (int) $r->id,
                'note' => (int) $r->note,
                'commentaire' => $r->commentaire ? (string) $r->commentaire : null,
                'auteur' => $auteur,
                'commande_titre' => $r->commande_titre ?: null,
                'created_at' => $r->created_at,
                'can_signaler' => $authClientId > 0 && ! $own && ! $signale,
                'signale' => $signale,
            ];
        })->values()->all();

        return response()->json([
            'ok' => true,
            'page' => $page,
            'per_page' => $perPage,
            'total' => $total,
            'last_page' => max(1, (int) ceil($total / $perPage)),
            'avis' => $avis,
            'stats' => $this->avisStats($id),
            'viewer' => [
                'role' => $request->session()->get('auth_type'),
                'is_client' => $authClientId > 0,
            ],
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function avisStats(int $prestataireId): array
    {
        if (! Schema::hasTable('avis')) {
            return $this->emptyAvisStats();
        }

        $rows = Avis::query()
            ->where('prestataire_id', $prestataireId)
            ->selectRaw('note, COUNT(*) as n')
            ->groupBy('note')
            ->pluck('n', 'note');

        $breakdown = [];
        $total = 0;
        $sum = 0;
        for ($i = 5; $i >= 1; $i--) {
            $count = (int) ($rows[$i] ?? 0);
            $breakdown[$i] = $count;
            $total += $count;
            $sum += $count * $i;
        }

        return [
            'total' => $total,
            'moyenne' => $total > 0 ? round($sum / $total, 2) : 0.0,
            'breakdown' => $breakdown,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function emptyAvisStats(): array
    {
        return ['total' => 0, 'moyenne' => 0.0, 'breakdown' => [5 => 0, 4 => 0, 3 => 0, 2 => 0, 1 => 0]];
    }

    private function publicServiceCard(Service $s, bool $withDetailUrl = false): array
    {
        $desc = (string) ($s->description ?? '');
        $excerpt = mb_substr(preg_replace('/\s+/', ' ', $desc), 0, 200);
        if (mb_strlen($desc) > 200) {
            $excerpt .= '…';
        }

        $photoPath = Schema::hasColumn('services', 'photo_path') ? ($s->photo_path ?? null) : null;

        $row = [
            'id' => $s->id,
            'titre' => $s->titre,
            'categorie' => $s->categorie,
            'prix_indicatif' => $s->prix_indicatif !== null ? (float) $s->prix_indicatif : null,
            'delai_execution' => $s->delai_execution,
            'description_excerpt' => $excerpt,
            'photo_url' => ImageStorage::publicUrl($photoPath),
        ];

        if ($withDetailUrl) {
            $row['detail_url'] = '/service/'.$s->id;
        }

        return $row;
    }

    private function bioExcerpt(string $bio, int $max): string
    {
        if ($bio === '') {
            return '';
        }
        if (mb_strlen($bio) <= $max) {
            return $bio;
        }

        return mb_substr($bio, 0, $max).'…';
    }

    private function prestataireIsPubliclyVisible(Prestataire $p): bool
    {
        if (Schema::hasColumn('prestataires', 'statut')) {
            $statut = (string) ($p->statut ?? 'actif');
            if ($statut !== 'actif' && $statut !== '') {
                return false;
            }
        }

        return $this->subscriptions->isDiscoverable($p);
    }

    /**
     * @param  \Illuminate\Database\Eloquent\Builder|\Illuminate\Database\Query\Builder  $query
     */
    private function scopeActiveAccount($query, string $alias = 'prestataires'): void
    {
        if (! Schema::hasColumn('prestataires', 'statut')) {
            return;
        }

        $col = str_contains($alias, '.') ? $alias : $alias.'.statut';
        $query->where(function ($w) use ($col) {
            $w->where($col, 'actif')->orWhereNull($col);
        });
    }

    /**
     * Masque les prestataires dont l'essai est terminé sans abonnement payant.
     *
     * @param  \Illuminate\Database\Eloquent\Builder|\Illuminate\Database\Query\Builder  $query
     */
    private function scopeDiscoverableSubscription($query, string $alias = 'p'): void
    {
        $sql = SubscriptionService::discoverableSqlCondition($alias);
        if ($sql === null) {
            return;
        }

        $query->whereRaw($sql);
    }

    /**
     * Priorité d'affichage selon le plan actif (Pro > Starter > Gratuit / expiré).
     */
    private function planPrioritySql(string $alias = 'p'): string
    {
        if (! Schema::hasColumn('prestataires', 'plan')) {
            return '0';
        }

        $plan = $alias.'.plan';
        $exp = $alias.'.plan_expires_at';

        return "CASE
            WHEN {$plan} = 'pro' AND {$exp} IS NOT NULL AND {$exp} > NOW() THEN 2
            WHEN {$plan} = 'starter' AND {$exp} IS NOT NULL AND {$exp} > NOW() THEN 1
            ELSE 0
        END";
    }
}
