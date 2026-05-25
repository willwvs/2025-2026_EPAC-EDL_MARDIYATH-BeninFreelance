<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Payout extends Model
{
    protected $table = 'payouts';

    const UPDATED_AT = null;

    protected $fillable = [
        'commande_id',
        'provider',
        'type',
        'destinataire_id',
        'phone',
        'withdraw_mode',
        'provider_ref',
        'montant_fcfa',
        'statut',
        'webhook_log',
        'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'created_at' => 'datetime',
            'completed_at' => 'datetime',
            'webhook_log' => 'array',
        ];
    }

    public function commande(): BelongsTo
    {
        return $this->belongsTo(Commande::class);
    }
}
