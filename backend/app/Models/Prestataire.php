<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Prestataire extends Model
{
    protected $table = 'prestataires';

    const UPDATED_AT = null;

    protected $fillable = [
        'nom',
        'prenom',
        'email',
        'telephone',
        'localite',
        'photo_profil',
        'biographie',
        'mot_de_passe',
        'password',
        'note_moyenne',
        'chemin_cv',
        'chemin_identite',
        'statut_verification',
        'motif_refus_ia',
        'email_verified_at',
        'email_verify_token',
        'email_verify_expires_at',
        'ia_decided_at',
        'admin_decided_at',
        'verification_email_sent_at',
        'mm_phone',
        'mm_mode',
    ];

    protected $hidden = [
        'mot_de_passe',
        'password',
        'email_verify_token',
    ];

    protected function casts(): array
    {
        return [
            'created_at' => 'datetime',
            'note_moyenne' => 'decimal:2',
            'email_verified_at' => 'datetime',
            'email_verify_expires_at' => 'datetime',
            'ia_decided_at' => 'datetime',
            'admin_decided_at' => 'datetime',
            'verification_email_sent_at' => 'datetime',
        ];
    }

    public function services(): HasMany
    {
        return $this->hasMany(Service::class);
    }

    public function messages(): HasMany
    {
        return $this->hasMany(Message::class);
    }

    public function avis(): HasMany
    {
        return $this->hasMany(Avis::class);
    }
}
