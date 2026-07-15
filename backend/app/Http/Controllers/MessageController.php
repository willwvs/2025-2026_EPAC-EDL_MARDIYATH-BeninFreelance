<?php

namespace App\Http\Controllers;

use App\Models\Client;
use App\Models\Message;
use App\Models\Prestataire;
use App\Models\Service;
use App\Services\SubscriptionService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class MessageController extends Controller
{
    public function __construct(
        private readonly SubscriptionService $subscriptions,
    ) {}

    public function contactContext(Request $request)
    {
        $prestataireId = (int) $request->query('prestataire_id', 0);
        $serviceId = (int) $request->query('service_id', 0);
        if ($prestataireId < 1 || $serviceId < 1) {
            return response()->json(['ok' => false, 'message' => 'Paramètres invalides.'], 422);
        }

        $ctx = $this->resolveServiceContext($prestataireId, $serviceId);
        if (! $ctx) {
            return response()->json(['ok' => false, 'message' => 'Prestataire ou service introuvable.'], 404);
        }

        $prestataire = Prestataire::query()->find($prestataireId);
        $canMessage = $prestataire ? $this->subscriptions->canMessage($prestataire) : false;

        return response()->json([
            'ok' => true,
            'prestataire' => $ctx['prestataire'],
            'service' => $ctx['service'],
            'can_reply' => $canMessage,
            'messaging_allowed' => $canMessage,
            'message' => $canMessage
                ? null
                : 'Ce prestataire ne peut pas recevoir de messages pour le moment (essai gratuit terminé ou abonnement requis).',
        ]);
    }

    public function indexClient(Request $request)
    {
        if (! Schema::hasTable('messages')) {
            return response()->json(['ok' => true, 'conversations' => []]);
        }

        $clientId = (int) $request->session()->get('auth_id');

        return response()->json([
            'ok' => true,
            'conversations' => $this->buildConversationsForClient($clientId),
        ]);
    }

    public function indexPrestataire(Request $request)
    {
        if (! Schema::hasTable('messages')) {
            return response()->json(['ok' => true, 'conversations' => []]);
        }

        $prestataireId = (int) $request->session()->get('auth_id');
        $prestataire = Prestataire::query()->find($prestataireId);
        $messagingAllowed = $prestataire ? $this->subscriptions->canMessage($prestataire) : false;

        return response()->json([
            'ok' => true,
            'messaging_allowed' => $messagingAllowed,
            'messaging_blocked_message' => $messagingAllowed
                ? null
                : $this->subscriptions->messagingBlockedMessage(),
            'conversations' => $this->buildConversationsForPrestataire($prestataireId),
        ]);
    }

    public function threadClient(Request $request)
    {
        if (! Schema::hasTable('messages')) {
            return response()->json(['ok' => true, 'messages' => []]);
        }

        $clientId = (int) $request->session()->get('auth_id');
        $prestataireId = (int) $request->query('prestataire_id', 0);
        $serviceId = (int) $request->query('service_id', 0);

        if ($prestataireId < 1 || $serviceId < 1) {
            return response()->json(['ok' => false, 'message' => 'Conversation invalide.'], 422);
        }

        if (! $this->threadExists($clientId, $prestataireId, $serviceId)) {
            return response()->json(['ok' => false, 'message' => 'Conversation introuvable.'], 404);
        }

        $this->markThreadRead($clientId, $prestataireId, $serviceId, 'client');

        return response()->json([
            'ok' => true,
            'thread' => $this->threadMetaForClient($clientId, $prestataireId, $serviceId),
            'messages' => $this->mapThreadMessages(
                $this->fetchThread($clientId, $prestataireId, $serviceId),
                'client'
            ),
        ]);
    }

    public function threadPrestataire(Request $request)
    {
        if (! Schema::hasTable('messages')) {
            return response()->json(['ok' => true, 'messages' => []]);
        }

        $prestataireId = (int) $request->session()->get('auth_id');
        $clientId = (int) $request->query('client_id', 0);
        $serviceId = (int) $request->query('service_id', 0);

        if ($clientId < 1 || $serviceId < 1) {
            return response()->json(['ok' => false, 'message' => 'Conversation invalide.'], 422);
        }

        if (! $this->threadExists($clientId, $prestataireId, $serviceId)) {
            return response()->json(['ok' => false, 'message' => 'Conversation introuvable.'], 404);
        }

        $this->markThreadRead($clientId, $prestataireId, $serviceId, 'prestataire');

        return response()->json([
            'ok' => true,
            'thread' => $this->threadMetaForPrestataire($clientId, $prestataireId, $serviceId),
            'messages' => $this->mapThreadMessages(
                $this->fetchThread($clientId, $prestataireId, $serviceId),
                'prestataire'
            ),
        ]);
    }

    public function storeClientMessage(Request $request)
    {
        if (! Schema::hasTable('messages')) {
            return response()->json(['ok' => false, 'message' => 'Messagerie non disponible.'], 503);
        }

        $clientId = (int) $request->session()->get('auth_id');
        $data = $request->validate([
            'prestataire_id' => 'required|integer|min:1',
            'service_id' => 'required|integer|min:1',
            'objet' => 'nullable|string|max:200',
            'contenu' => 'nullable|required_without:attachment|string|max:8000',
            'attachment' => 'nullable|file|max:10240|mimes:jpg,jpeg,png,webp,pdf,doc,docx,xls,xlsx,txt,zip',
        ]);

        $prestataireId = (int) $data['prestataire_id'];
        $serviceId = (int) $data['service_id'];

        if (! $this->resolveServiceContext($prestataireId, $serviceId)) {
            return response()->json(['ok' => false, 'message' => 'Prestataire ou service introuvable.'], 404);
        }

        $prestataire = Prestataire::query()->find($prestataireId);
        if (! $prestataire || ! $this->subscriptions->canMessage($prestataire)) {
            return response()->json([
                'ok' => false,
                'message' => 'Ce prestataire ne peut pas recevoir de messages pour le moment (essai gratuit terminé ou abonnement requis).',
            ], 403);
        }

        $isFirst = ! $this->threadExists($clientId, $prestataireId, $serviceId);
        $body = trim((string) ($data['contenu'] ?? ''));
        $attachment = $this->storeAttachment($request);

        if ($isFirst) {
            $objet = isset($data['objet']) ? trim((string) $data['objet']) : '';
            if ($objet !== '') {
                $body = 'Objet : '.$objet.($body !== '' ? "\n\n".$body : '');
            }
            $client = Client::query()->where('id', $clientId)->first();
            if ($client) {
                $tel = trim((string) ($client->telephone ?? ''));
                $snapshot = "\n\n—\nEnvoyé par : ".trim($client->prenom.' '.$client->nom);
                $snapshot .= ' <'.$client->email.'>';
                if ($tel !== '') {
                    $snapshot .= ' · '.$tel;
                }
                $body .= ($body !== '' ? '' : 'Pièce jointe').$snapshot;
            }
        }

        $this->insertMessage($clientId, $prestataireId, $serviceId, 'client', $body, $attachment);

        return response()->json([
            'ok' => true,
            'message' => $isFirst ? 'Votre demande a été envoyée.' : 'Message envoyé.',
            'is_first' => $isFirst,
        ]);
    }

    public function storePrestataireMessage(Request $request)
    {
        if (! Schema::hasTable('messages')) {
            return response()->json(['ok' => false, 'message' => 'Messagerie non disponible.'], 503);
        }

        $prestataireId = (int) $request->session()->get('auth_id');
        $data = $request->validate([
            'client_id' => 'required|integer|min:1',
            'service_id' => 'required|integer|min:1',
            'contenu' => 'nullable|required_without:attachment|string|max:8000',
            'attachment' => 'nullable|file|max:10240|mimes:jpg,jpeg,png,webp,pdf,doc,docx,xls,xlsx,txt,zip',
        ]);

        $prestataire = Prestataire::query()->find($prestataireId);
        if (! $prestataire || ! $this->subscriptions->canMessage($prestataire)) {
            return response()->json([
                'ok' => false,
                'message' => $this->subscriptions->messagingBlockedMessage(),
            ], 403);
        }

        $clientId = (int) $data['client_id'];
        $serviceId = (int) $data['service_id'];

        if (! $this->threadExists($clientId, $prestataireId, $serviceId)) {
            return response()->json(['ok' => false, 'message' => 'Conversation introuvable.'], 404);
        }

        $svc = Service::query()
            ->where('id', $serviceId)
            ->where('prestataire_id', $prestataireId)
            ->first();
        if (! $svc) {
            return response()->json(['ok' => false, 'message' => 'Service invalide.'], 422);
        }

        $this->insertMessage(
            $clientId,
            $prestataireId,
            $serviceId,
            'prestataire',
            trim((string) ($data['contenu'] ?? '')),
            $this->storeAttachment($request)
        );

        return response()->json(['ok' => true, 'message' => 'Message envoyé.']);
    }

    public function updateClientMessage(Request $request, int $id)
    {
        return $this->updateChatMessage($request, $id, 'client');
    }

    public function deleteClientMessage(Request $request, int $id)
    {
        return $this->deleteChatMessage($request, $id, 'client');
    }

    public function updatePrestataireMessage(Request $request, int $id)
    {
        return $this->updateChatMessage($request, $id, 'prestataire');
    }

    public function deletePrestataireMessage(Request $request, int $id)
    {
        return $this->deleteChatMessage($request, $id, 'prestataire');
    }

    public function attachment(Request $request, int $id)
    {
        $message = Message::query()->where('id', $id)->first();
        if (! $message || empty($message->attachment_path)) {
            abort(404);
        }

        $authType = (string) $request->session()->get('auth_type');
        $authId = (int) $request->session()->get('auth_id');
        $allowed = ($authType === 'client' && (int) $message->client_id === $authId)
            || ($authType === 'prestataire' && (int) $message->prestataire_id === $authId);

        if (! $allowed || ! Storage::disk('local')->exists((string) $message->attachment_path)) {
            abort(404);
        }

        $path = (string) $message->attachment_path;
        $name = $message->attachment_name ?: 'piece-jointe';
        $mime = $message->attachment_mime ? (string) $message->attachment_mime : null;
        $inline = $request->boolean('inline') || ($mime && str_starts_with($mime, 'image/'));

        if ($inline) {
            return Storage::disk('local')->response($path, $name, [
                'Content-Type' => $mime ?: 'application/octet-stream',
                'Content-Disposition' => 'inline; filename="'.$name.'"',
            ]);
        }

        return Storage::disk('local')->download($path, $name);
    }

    /** @return array<int, array<string, mixed>> */
    private function buildConversationsForClient(int $clientId): array
    {
        $latestIds = Message::query()
            ->where('client_id', $clientId)
            ->selectRaw('MAX(id) as id')
            ->groupBy('prestataire_id', 'service_id')
            ->pluck('id');

        if ($latestIds->isEmpty()) {
            return [];
        }

        $rows = Message::query()
            ->from('messages as m')
            ->join('prestataires as p', 'p.id', '=', 'm.prestataire_id')
            ->join('services as s', 's.id', '=', 'm.service_id')
            ->whereIn('m.id', $latestIds)
            ->orderByDesc('m.id')
            ->get([
                'm.prestataire_id',
                'm.service_id',
                'm.contenu',
                'm.attachment_name',
                'm.created_at',
                'm.expediteur',
                'm.deleted_at',
                'p.nom as prestataire_nom',
                'p.prenom as prestataire_prenom',
                's.titre as service_titre',
            ]);

        return $rows->map(function ($r) use ($clientId) {
            return [
                'prestataire_id' => (int) $r->prestataire_id,
                'service_id' => (int) $r->service_id,
                'peer_label' => trim($r->prestataire_prenom.' '.$r->prestataire_nom),
                'service_titre' => $r->service_titre,
                'last_excerpt' => $this->excerptForList(
                    (string) $r->contenu,
                    $r->attachment_name,
                    Schema::hasColumn('messages', 'deleted_at') && ! empty($r->deleted_at)
                ),
                'last_at' => $r->created_at,
                'last_is_mine' => $this->senderOf($r) === 'client',
                'unread_count' => $this->unreadCount($clientId, (int) $r->prestataire_id, (int) $r->service_id, 'client'),
            ];
        })->values()->all();
    }

    /** @return array<int, array<string, mixed>> */
    private function buildConversationsForPrestataire(int $prestataireId): array
    {
        $latestIds = Message::query()
            ->where('prestataire_id', $prestataireId)
            ->selectRaw('MAX(id) as id')
            ->groupBy('client_id', 'service_id')
            ->pluck('id');

        if ($latestIds->isEmpty()) {
            return [];
        }

        $rows = Message::query()
            ->from('messages as m')
            ->join('clients as c', 'c.id', '=', 'm.client_id')
            ->join('services as s', 's.id', '=', 'm.service_id')
            ->whereIn('m.id', $latestIds)
            ->orderByDesc('m.id')
            ->get([
                'm.client_id',
                'm.prestataire_id',
                'm.service_id',
                'm.contenu',
                'm.attachment_name',
                'm.created_at',
                'm.expediteur',
                'm.deleted_at',
                'c.nom as client_nom',
                'c.prenom as client_prenom',
                's.titre as service_titre',
            ]);

        return $rows->map(function ($r) use ($prestataireId) {
            return [
                'client_id' => (int) $r->client_id,
                'prestataire_id' => (int) $r->prestataire_id,
                'service_id' => (int) $r->service_id,
                'peer_label' => trim($r->client_prenom.' '.$r->client_nom),
                'service_titre' => $r->service_titre,
                'last_excerpt' => $this->excerptForList(
                    (string) $r->contenu,
                    $r->attachment_name,
                    Schema::hasColumn('messages', 'deleted_at') && ! empty($r->deleted_at)
                ),
                'last_at' => $r->created_at,
                'last_is_mine' => $this->senderOf($r) === 'prestataire',
                'unread_count' => $this->unreadCount((int) $r->client_id, $prestataireId, (int) $r->service_id, 'prestataire'),
            ];
        })->values()->all();
    }

  /** @return \Illuminate\Database\Eloquent\Collection<int, Message> */
    private function fetchThread(int $clientId, int $prestataireId, int $serviceId)
    {
        return Message::query()
            ->where('client_id', $clientId)
            ->where('prestataire_id', $prestataireId)
            ->where('service_id', $serviceId)
            ->orderBy('id')
            ->get();
    }

    /**
     * @param  \Illuminate\Database\Eloquent\Collection<int, Message>  $rows
     * @return array<int, array<string, mixed>>
     */
    private function mapThreadMessages($rows, string $viewerRole): array
    {
        return $rows->map(function (Message $m) use ($viewerRole) {
            $sender = $this->senderOf($m);
            $kind = Schema::hasColumn('messages', 'kind') ? ((string) ($m->kind ?: 'chat')) : 'chat';
            $meta = Schema::hasColumn('messages', 'meta') && $m->meta ? $m->meta : null;
            $deleted = Schema::hasColumn('messages', 'deleted_at') && $m->deleted_at !== null;
            $editedAt = Schema::hasColumn('messages', 'edited_at') ? $m->edited_at : null;
            $isChat = $kind === 'chat' || $kind === '';
            $mine = $sender === $viewerRole;

            return [
                'id' => $m->id,
                'expediteur' => $sender,
                'kind' => $kind,
                'commande_id' => Schema::hasColumn('messages', 'commande_id') ? ($m->commande_id ?? null) : null,
                'contenu' => $deleted ? '' : $this->bodyForBubble((string) $m->contenu),
                'deleted' => $deleted,
                'edited_at' => $editedAt,
                'can_edit' => $mine && $isChat && ! $deleted,
                'can_delete' => $mine && $isChat && ! $deleted,
                'meta' => $meta,
                'attachment' => $deleted ? null : $this->attachmentPayload($m),
                'created_at' => $m->created_at,
                'mine' => $mine,
            ];
        })->values()->all();
    }

    private function threadExists(int $clientId, int $prestataireId, int $serviceId): bool
    {
        return Message::query()
            ->where('client_id', $clientId)
            ->where('prestataire_id', $prestataireId)
            ->where('service_id', $serviceId)
            ->exists();
    }

    /**
     * @param  array{path:string,name:string,mime:?string,size:int}|null  $attachment
     */
    private function insertMessage(
        int $clientId,
        int $prestataireId,
        int $serviceId,
        string $expediteur,
        string $contenu,
        ?array $attachment = null
    ): void
    {
        $insert = [
            'client_id' => $clientId,
            'prestataire_id' => $prestataireId,
            'service_id' => $serviceId,
            'contenu' => $contenu,
            'lu' => 0,
        ];

        if (Schema::hasColumn('messages', 'expediteur')) {
            $insert['expediteur'] = $expediteur;
        }

        if ($attachment && Schema::hasColumn('messages', 'attachment_path')) {
            $insert['attachment_path'] = $attachment['path'];
            $insert['attachment_name'] = $attachment['name'];
            $insert['attachment_mime'] = $attachment['mime'];
            $insert['attachment_size'] = $attachment['size'];
        }

        if (Schema::hasColumn('messages', 'created_at')) {
            $insert['created_at'] = now();
        }

        Message::query()->insert($insert);
    }

    private function markThreadRead(int $clientId, int $prestataireId, int $serviceId, string $viewerRole): void
    {
        $q = Message::query()
            ->where('client_id', $clientId)
            ->where('prestataire_id', $prestataireId)
            ->where('service_id', $serviceId)
            ->where('lu', 0);

        if (Schema::hasColumn('messages', 'expediteur')) {
            $q->where('expediteur', '!=', $viewerRole);
        } elseif ($viewerRole === 'prestataire') {
            return;
        }

        $q->update(['lu' => 1]);
    }

    private function unreadCount(int $clientId, int $prestataireId, int $serviceId, string $viewerRole): int
    {
        $q = Message::query()
            ->where('client_id', $clientId)
            ->where('prestataire_id', $prestataireId)
            ->where('service_id', $serviceId)
            ->where('lu', 0);

        if (Schema::hasColumn('messages', 'expediteur')) {
            $q->where('expediteur', '!=', $viewerRole);
        } elseif ($viewerRole === 'client') {
            return 0;
        }

        return (int) $q->count();
    }

    private function senderOf(object $row): string
    {
        if (Schema::hasColumn('messages', 'expediteur') && ! empty($row->expediteur)) {
            $v = (string) $row->expediteur;
            if ($v === 'prestataire' || $v === 'system') {
                return $v;
            }

            return 'client';
        }

        return 'client';
    }

    /** @return array{prestataire: array<string, mixed>, service: array<string, mixed>}|null */
    private function resolveServiceContext(int $prestataireId, int $serviceId): ?array
    {
        $p = Prestataire::query()
            ->where('id', $prestataireId)
            ->where('statut_verification', 'valide')
            ->first();
        if (! $p) {
            return null;
        }

        $svc = Service::query()
            ->where('id', $serviceId)
            ->where('prestataire_id', $prestataireId)
            ->first();
        if (! $svc) {
            return null;
        }

        return [
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
        ];
    }

    /** @return array<string, mixed> */
    private function threadMetaForClient(int $clientId, int $prestataireId, int $serviceId): array
    {
        $ctx = $this->resolveServiceContext($prestataireId, $serviceId);
        $p = $ctx['prestataire'] ?? [];

        return [
            'prestataire_id' => $prestataireId,
            'service_id' => $serviceId,
            'peer_label' => trim(($p['prenom'] ?? '').' '.($p['nom'] ?? '')),
            'service_titre' => $ctx['service']['titre'] ?? '',
        ];
    }

    /** @return array<string, mixed> */
    private function threadMetaForPrestataire(int $clientId, int $prestataireId, int $serviceId): array
    {
        $c = Client::query()->where('id', $clientId)->first();
        $svc = Service::query()->where('id', $serviceId)->where('prestataire_id', $prestataireId)->first();

        return [
            'client_id' => $clientId,
            'prestataire_id' => $prestataireId,
            'service_id' => $serviceId,
            'peer_label' => $c ? trim($c->prenom.' '.$c->nom) : 'Client',
            'service_titre' => $svc?->titre ?? '',
        ];
    }

    /**
     * @return array{path:string,name:string,mime:?string,size:int}|null
     */
    private function storeAttachment(Request $request): ?array
    {
        if (! $request->hasFile('attachment') || ! Schema::hasColumn('messages', 'attachment_path')) {
            return null;
        }

        $file = $request->file('attachment');
        if (! $file || ! $file->isValid()) {
            return null;
        }

        $original = $file->getClientOriginalName() ?: 'piece-jointe';
        $ext = strtolower($file->getClientOriginalExtension());
        $name = (string) Str::uuid().($ext !== '' ? '.'.$ext : '');
        $path = $file->storeAs('messages/attachments', $name, 'local');

        return [
            'path' => $path,
            'name' => $original,
            'mime' => $file->getClientMimeType(),
            'size' => (int) $file->getSize(),
        ];
    }

    /** @return array{name:string,mime:?string,size:?int,url:string,is_image:bool}|null */
    private function attachmentPayload(Message $message): ?array
    {
        if (empty($message->attachment_path)) {
            return null;
        }

        $mime = $message->attachment_mime ? (string) $message->attachment_mime : null;

        return [
            'name' => $message->attachment_name ?: 'Pièce jointe',
            'mime' => $mime,
            'size' => $message->attachment_size ? (int) $message->attachment_size : null,
            'url' => '/api/messages/'.$message->id.'/attachment',
            'preview_url' => '/api/messages/'.$message->id.'/attachment?inline=1',
            'is_image' => $mime !== null && str_starts_with($mime, 'image/'),
        ];
    }

    private function excerptForList(string $contenu, ?string $attachmentName = null, bool $deleted = false): string
    {
        if ($deleted) {
            return 'Message supprimé';
        }

        $cut = $this->bodyForBubble($contenu);
        $cut = trim(preg_replace('/\s+/', ' ', $cut));
        if ($cut === '' && $attachmentName) {
            return 'Pièce jointe : '.$attachmentName;
        }
        if (mb_strlen($cut) > 120) {
            return mb_substr($cut, 0, 120).'…';
        }

        return $cut;
    }

    private function bodyForBubble(string $contenu): string
    {
        $pos = mb_strpos($contenu, "\n\n—\n");
        if ($pos !== false && $pos > 0) {
            return trim(mb_substr($contenu, 0, $pos));
        }

        return trim($contenu);
    }

    private function updateChatMessage(Request $request, int $id, string $role)
    {
        if (! Schema::hasTable('messages')) {
            return response()->json(['ok' => false, 'message' => 'Messagerie non disponible.'], 503);
        }

        $message = $this->resolveOwnedChatMessage($request, $id, $role);
        if ($message instanceof \Illuminate\Http\JsonResponse) {
            return $message;
        }

        $data = $request->validate([
            'contenu' => 'required|string|max:8000',
        ]);

        $body = trim((string) $data['contenu']);
        if ($body === '') {
            return response()->json(['ok' => false, 'message' => 'Le message ne peut pas être vide.'], 422);
        }

        $update = ['contenu' => $this->mergeEditedBody((string) $message->contenu, $body)];
        if (Schema::hasColumn('messages', 'edited_at')) {
            $update['edited_at'] = now();
        }

        $message->update($update);

        return response()->json(['ok' => true, 'message' => 'Message modifié.']);
    }

    private function deleteChatMessage(Request $request, int $id, string $role)
    {
        if (! Schema::hasTable('messages')) {
            return response()->json(['ok' => false, 'message' => 'Messagerie non disponible.'], 503);
        }

        $message = $this->resolveOwnedChatMessage($request, $id, $role);
        if ($message instanceof \Illuminate\Http\JsonResponse) {
            return $message;
        }

        if (! empty($message->attachment_path)) {
            Storage::disk('local')->delete((string) $message->attachment_path);
        }

        $update = ['contenu' => ''];
        if (Schema::hasColumn('messages', 'deleted_at')) {
            $update['deleted_at'] = now();
        }
        if (Schema::hasColumn('messages', 'attachment_path')) {
            $update['attachment_path'] = null;
            $update['attachment_name'] = null;
            $update['attachment_mime'] = null;
            $update['attachment_size'] = null;
        }

        $message->update($update);

        return response()->json(['ok' => true, 'message' => 'Message supprimé.']);
    }

    /** @return Message|\Illuminate\Http\JsonResponse */
    private function resolveOwnedChatMessage(Request $request, int $id, string $role)
    {
        $authId = (int) $request->session()->get('auth_id');
        $message = Message::query()->where('id', $id)->first();

        if (! $message) {
            return response()->json(['ok' => false, 'message' => 'Message introuvable.'], 404);
        }

        if (Schema::hasColumn('messages', 'deleted_at') && $message->deleted_at !== null) {
            return response()->json(['ok' => false, 'message' => 'Message déjà supprimé.'], 422);
        }

        $kind = Schema::hasColumn('messages', 'kind') ? ((string) ($message->kind ?: 'chat')) : 'chat';
        if ($kind !== 'chat' && $kind !== '') {
            return response()->json(['ok' => false, 'message' => 'Ce message ne peut pas être modifié.'], 422);
        }

        $sender = $this->senderOf($message);
        if ($sender !== $role) {
            return response()->json(['ok' => false, 'message' => 'Action non autorisée.'], 403);
        }

        if ($role === 'client' && (int) $message->client_id !== $authId) {
            return response()->json(['ok' => false, 'message' => 'Action non autorisée.'], 403);
        }
        if ($role === 'prestataire' && (int) $message->prestataire_id !== $authId) {
            return response()->json(['ok' => false, 'message' => 'Action non autorisée.'], 403);
        }

        return $message;
    }

    private function mergeEditedBody(string $stored, string $newVisibleBody): string
    {
        $pos = mb_strpos($stored, "\n\n—\n");
        if ($pos !== false && $pos > 0) {
            return trim($newVisibleBody).mb_substr($stored, $pos);
        }

        return trim($newVisibleBody);
    }
}
