<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Service extends Model
{
    protected $table = 'services';

    const UPDATED_AT = null;

    protected $fillable = [
        'prestataire_id',
        'titre',
        'description',
        'categorie',
        'prix_indicatif',
        'delai_execution',
    ];

    protected function casts(): array
    {
        return [
            'created_at' => 'datetime',
            'prix_indicatif' => 'decimal:2',
        ];
    }

    public function prestataire(): BelongsTo
    {
        return $this->belongsTo(Prestataire::class);
    }

    public function messages(): HasMany
    {
        return $this->hasMany(Message::class);
    }
}
