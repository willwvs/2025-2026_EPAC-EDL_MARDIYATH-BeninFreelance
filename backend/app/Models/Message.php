<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Message extends Model
{
    protected $table = 'messages';

    const UPDATED_AT = null;

    protected $fillable = [
        'client_id',
        'prestataire_id',
        'service_id',
        'commande_id',
        'expediteur',
        'kind',
        'contenu',
        'meta',
        'attachment_path',
        'attachment_name',
        'attachment_mime',
        'attachment_size',
        'lu',
        'edited_at',
        'deleted_at',
    ];

    protected function casts(): array
    {
        return [
            'created_at' => 'datetime',
            'edited_at' => 'datetime',
            'deleted_at' => 'datetime',
            'lu' => 'boolean',
            'meta' => 'array',
        ];
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function prestataire(): BelongsTo
    {
        return $this->belongsTo(Prestataire::class);
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }
}
