<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Avis extends Model
{
    protected $table = 'avis';

    const UPDATED_AT = null;

    protected $fillable = [
        'client_id',
        'prestataire_id',
        'commande_id',
        'note',
        'commentaire',
        'signale',
        'signale_motif',
        'signale_at',
    ];

    protected function casts(): array
    {
        return [
            'created_at' => 'datetime',
            'signale_at' => 'datetime',
            'signale' => 'boolean',
            'note' => 'integer',
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
}
