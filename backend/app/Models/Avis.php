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
        'note',
        'commentaire',
    ];

    protected function casts(): array
    {
        return [
            'created_at' => 'datetime',
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
