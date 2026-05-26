<?php

namespace App\Support;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;

class AccountStatus
{
    public const ACTIF = 'actif';

    public const SUSPENDU = 'suspendu';

    public const SUPPRIME = 'supprime';

    public static function isActive(Model $user, string $table): bool
    {
        if (! Schema::hasColumn($table, 'statut')) {
            return true;
        }

        $statut = (string) ($user->statut ?? self::ACTIF);

        return $statut === self::ACTIF;
    }

    public static function blockMessage(string $statut): string
    {
        if ($statut === self::SUSPENDU) {
            return 'Votre compte a été suspendu. Contactez le support.';
        }

        if ($statut === self::SUPPRIME) {
            return 'Ce compte n\'est plus actif.';
        }

        return 'Accès refusé.';
    }
}
