<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Paiement extends Model
{
    protected $table = 'paiements';

    const UPDATED_AT = null;

    protected $fillable = [
        'commande_id',
        'provider',
        'provider_ref',
        'provider_token',
        'payment_url',
        'montant_fcfa',
        'frais_fcfa',
        'moyen',
        'statut',
        'webhook_log',
        'paid_at',
    ];

    protected function casts(): array
    {
        return [
            'created_at' => 'datetime',
            'paid_at' => 'datetime',
            'webhook_log' => 'array',
        ];
    }

    public function commande(): BelongsTo
    {
        return $this->belongsTo(Commande::class);
    }
}
