<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Abonnement extends Model
{
    protected $table = 'abonnements';

    const UPDATED_AT = null;

    protected $fillable = [
        'prestataire_id',
        'plan',
        'montant_fcfa',
        'periode_jours',
        'fedapay_transaction_id',
        'payment_url',
        'provider_token',
        'statut',
        'starts_at',
        'ends_at',
        'webhook_log',
    ];

    protected function casts(): array
    {
        return [
            'created_at' => 'datetime',
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'webhook_log' => 'array',
            'montant_fcfa' => 'integer',
            'periode_jours' => 'integer',
        ];
    }

    public function prestataire(): BelongsTo
    {
        return $this->belongsTo(Prestataire::class);
    }
}
