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
    ];

    protected $hidden = [
        'mot_de_passe',
        'password',
    ];

    protected function casts(): array
    {
        return [
            'created_at' => 'datetime',
            'note_moyenne' => 'decimal:2',
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
