<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Category extends Model
{
    protected $table = 'categories';

    const UPDATED_AT = null;

    protected $fillable = [
        'nom',
        'slug',
        'ordre',
        'active',
    ];

    protected function casts(): array
    {
        return [
            'active' => 'boolean',
            'ordre' => 'integer',
            'created_at' => 'datetime',
        ];
    }

    public static function activeNames(): array
    {
        if (! \Illuminate\Support\Facades\Schema::hasTable('categories')) {
            return [];
        }

        return static::query()
            ->where('active', true)
            ->orderBy('ordre')
            ->orderBy('nom')
            ->pluck('nom')
            ->all();
    }

    protected static function booted(): void
    {
        static::saving(function (Category $cat) {
            if ($cat->nom && ! $cat->slug) {
                $cat->slug = Str::slug($cat->nom);
            }
        });
    }
}
