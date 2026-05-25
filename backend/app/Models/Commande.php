<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Commande extends Model
{
    protected $table = 'commandes';

    const UPDATED_AT = null;

    protected $fillable = [
        'client_id',
        'prestataire_id',
        'service_id',
        'titre',
        'description',
        'montant_fcfa',
        'delai_jours',
        'statut',
        'commission_fcfa',
        'net_prestataire_fcfa',
        'motif_refus',
        'motif_annulation',
        'acceptee_at',
        'payee_at',
        'livree_at',
        'review_until',
        'terminee_at',
        'paid_out_at',
        'remboursee_at',
        'litige_at',
        'litige_motif',
        'litige_resolution',
        'litige_resolution_note',
        'litige_resolved_at',
        'reminders_sent',
    ];

    protected function casts(): array
    {
        return [
            'created_at' => 'datetime',
            'acceptee_at' => 'datetime',
            'payee_at' => 'datetime',
            'livree_at' => 'datetime',
            'review_until' => 'datetime',
            'terminee_at' => 'datetime',
            'paid_out_at' => 'datetime',
            'remboursee_at' => 'datetime',
            'litige_at' => 'datetime',
            'litige_resolved_at' => 'datetime',
            'reminders_sent' => 'array',
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

    public function paiement(): HasOne
    {
        return $this->hasOne(Paiement::class);
    }

    public function payouts(): HasMany
    {
        return $this->hasMany(Payout::class);
    }

    public function livraisons(): HasMany
    {
        return $this->hasMany(CommandeLivraison::class);
    }
}
