<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Prestataire;
use App\Models\Service;
use App\Services\SubscriptionService;
use App\Support\ImageStorage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;

class ServiceController extends Controller
{
    public function __construct(
        private readonly SubscriptionService $subscriptions,
    ) {}

    public function indexMine(Request $request)
    {
        $prestataireId = (int) $request->session()->get('auth_id');
        $rows = Service::query()
            ->where('prestataire_id', $prestataireId)
            ->orderByDesc('id')
            ->get()
            ->map(fn ($s) => $this->serviceRow($s))
            ->values();

        return response()->json(['ok' => true, 'rows' => $rows]);
    }

    public function store(Request $request)
    {
        $prestataireId = (int) $request->session()->get('auth_id');
        $data = $request->validate(array_merge([
            'titre' => 'required|string|max:200',
            'description' => 'required|string|max:10000',
            'prix_indicatif' => 'nullable|numeric|min:0',
            'delai_execution' => 'nullable|string|max:100',
        ], $this->categoryRules()));

        $prestataire = Prestataire::query()->find($prestataireId);
        $plan = $prestataire ? $this->subscriptions->effectivePlan($prestataire) : 'gratuit';
        $max = SubscriptionService::servicesMax($plan);
        $existing = (int) Service::query()->where('prestataire_id', $prestataireId)->count();

        if ($max !== null && $existing >= $max) {
            $label = SubscriptionService::planLabel($plan);

            return response()->json([
                'ok' => false,
                'message' => "Limite du plan {$label} ({$max} services) atteinte. Passez à un plan supérieur ou supprimez un service.",
            ], 422);
        }

        $payload = [
            'prestataire_id' => $prestataireId,
            'titre' => $data['titre'],
            'description' => $data['description'],
            'categorie' => $data['categorie'],
            'prix_indicatif' => $data['prix_indicatif'] ?? null,
            'delai_execution' => $data['delai_execution'] ?? null,
        ];

        $service = Service::create($payload);

        return response()->json([
            'ok' => true,
            'id' => $service->id,
            'service' => $this->serviceRow($service),
        ], 201);
    }

    public function update(Request $request, int $id)
    {
        $prestataireId = (int) $request->session()->get('auth_id');
        $service = Service::query()
            ->where('id', $id)
            ->where('prestataire_id', $prestataireId)
            ->first();
        if (! $service) {
            return response()->json(['ok' => false, 'message' => 'Service introuvable.'], 404);
        }

        $data = $request->validate(array_merge([
            'titre' => 'required|string|max:200',
            'description' => 'required|string|max:10000',
            'prix_indicatif' => 'nullable|numeric|min:0',
            'delai_execution' => 'nullable|string|max:100',
        ], $this->categoryRules()));

        $service->update([
            'titre' => $data['titre'],
            'description' => $data['description'],
            'categorie' => $data['categorie'],
            'prix_indicatif' => $data['prix_indicatif'] ?? null,
            'delai_execution' => $data['delai_execution'] ?? null,
        ]);

        return response()->json(['ok' => true, 'service' => $this->serviceRow($service->fresh())]);
    }

    public function uploadPhoto(Request $request, int $id)
    {
        $request->validate([
            'photo' => 'required|file|max:5120',
        ]);

        $prestataireId = (int) $request->session()->get('auth_id');
        $service = Service::query()
            ->where('id', $id)
            ->where('prestataire_id', $prestataireId)
            ->first();
        if (! $service) {
            return response()->json(['ok' => false, 'message' => 'Service introuvable.'], 404);
        }

        if (! Schema::hasColumn('services', 'photo_path')) {
            return response()->json([
                'ok' => false,
                'message' => 'Colonne photo_path absente. Exécutez la migration SQL des services.',
            ], 503);
        }

        try {
            ImageStorage::deletePublicPath($service->photo_path);
            $stored = ImageStorage::storeImage($request->file('photo'), 'uploads/services');
        } catch (\Throwable $e) {
            return response()->json(['ok' => false, 'message' => $e->getMessage()], 422);
        }

        $service->update(['photo_path' => $stored['path']]);

        return response()->json([
            'ok' => true,
            'photo_url' => $stored['public_url'],
            'service' => $this->serviceRow($service->fresh()),
        ]);
    }

    public function destroy(Request $request, int $id)
    {
        $prestataireId = (int) $request->session()->get('auth_id');
        $service = Service::query()
            ->where('id', $id)
            ->where('prestataire_id', $prestataireId)
            ->first();
        if (! $service) {
            return response()->json(['ok' => false, 'message' => 'Service introuvable.'], 404);
        }

        ImageStorage::deletePublicPath($service->photo_path);
        $service->delete();

        return response()->json(['ok' => true]);
    }

    /**
     * @return array<string, array<int, mixed>|string>
     */
    private function categoryRules(): array
    {
        $names = Category::activeNames();
        if ($names === []) {
            return ['categorie' => 'required|string|max:100'];
        }

        return ['categorie' => ['required', 'string', 'max:100', Rule::in($names)]];
    }

    private function serviceRow(Service $s): array
    {
        $photoPath = Schema::hasColumn('services', 'photo_path') ? $s->photo_path : null;

        return [
            'id' => $s->id,
            'prestataire_id' => $s->prestataire_id,
            'titre' => $s->titre,
            'description' => $s->description,
            'categorie' => $s->categorie,
            'prix_indicatif' => $s->prix_indicatif !== null ? (float) $s->prix_indicatif : null,
            'delai_execution' => $s->delai_execution,
            'photo_url' => ImageStorage::publicUrl($photoPath),
            'created_at' => $s->created_at,
        ];
    }
}
