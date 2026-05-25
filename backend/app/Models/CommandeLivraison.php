<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CommandeLivraison extends Model
{
    protected $table = 'commande_livraisons';

    const UPDATED_AT = null;

    protected $fillable = [
        'commande_id',
        'version',
        'message',
        'attachments',
        'attachment_path',
        'attachment_name',
        'attachment_size',
    ];

    protected function casts(): array
    {
        return [
            'created_at' => 'datetime',
            'attachments' => 'array',
        ];
    }

    /**
     * Liste normalisée des pièces jointes (multi + legacy).
     *
     * @return array<int, array{path: string, name: string, size: int, mime: ?string}>
     */
    public function attachmentList(): array
    {
        $items = [];
        if (is_array($this->attachments)) {
            foreach ($this->attachments as $a) {
                if (! is_array($a) || empty($a['path'])) {
                    continue;
                }
                $items[] = [
                    'path' => (string) $a['path'],
                    'name' => (string) ($a['name'] ?? 'fichier'),
                    'size' => (int) ($a['size'] ?? 0),
                    'mime' => isset($a['mime']) ? (string) $a['mime'] : null,
                ];
            }
        }

        if (empty($items) && $this->attachment_path) {
            $items[] = [
                'path' => (string) $this->attachment_path,
                'name' => (string) ($this->attachment_name ?: 'fichier'),
                'size' => (int) ($this->attachment_size ?: 0),
                'mime' => null,
            ];
        }

        return $items;
    }

    public function commande(): BelongsTo
    {
        return $this->belongsTo(Commande::class);
    }
}
