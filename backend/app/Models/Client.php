<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Client extends Model
{
    protected $table = 'clients';

    /** Schéma SQL : pas de updated_at */
    const UPDATED_AT = null;

    protected $fillable = [
        'nom',
        'prenom',
        'email',
        'telephone',
        'localite',
        'mot_de_passe',
        'password',
        'email_verified_at',
        'email_verify_token',
        'email_verify_expires_at',
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
            'email_verified_at' => 'datetime',
            'email_verify_expires_at' => 'datetime',
        ];
    }

    public function messages(): HasMany
    {
        return $this->hasMany(Message::class);
    }
}
