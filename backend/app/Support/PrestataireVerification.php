<?php

namespace App\Support;

use App\Models\Prestataire;
use Carbon\Carbon;

class PrestataireVerification
{
  /** Délai (heures) avant confirmation automatique d'une décision IA sans action admin. */
    public const ADMIN_REVIEW_HOURS = 24;

    /**
     * Statut effectif pour connexion / affichage : en_attente | valide | refuse.
     */
    public static function effectiveStatus(Prestataire $p): string
    {
        if ($p->admin_decided_at) {
            return (string) ($p->statut_verification ?? 'en_attente');
        }

        $raw = (string) ($p->statut_verification ?? 'en_attente');
        if ($raw === 'en_attente' || ! $p->ia_decided_at) {
            return 'en_attente';
        }

        $iaAt = $p->ia_decided_at instanceof Carbon
            ? $p->ia_decided_at
            : Carbon::parse($p->ia_decided_at);

        if ($iaAt->gt(now()->subHours(self::ADMIN_REVIEW_HOURS))) {
            return 'en_attente';
        }

        return in_array($raw, ['valide', 'refuse'], true) ? $raw : 'en_attente';
    }

    public static function canLogin(Prestataire $p): bool
    {
        return self::effectiveStatus($p) === 'valide'
            && $p->email_verified_at !== null;
    }

    public static function shouldAutoFinalize(Prestataire $p): bool
    {
        if ($p->admin_decided_at || ! $p->ia_decided_at) {
            return false;
        }

        $raw = (string) ($p->statut_verification ?? 'en_attente');
        if (! in_array($raw, ['valide', 'refuse'], true)) {
            return false;
        }

        $iaAt = $p->ia_decided_at instanceof Carbon
            ? $p->ia_decided_at
            : Carbon::parse($p->ia_decided_at);

        return $iaAt->lte(now()->subHours(self::ADMIN_REVIEW_HOURS));
    }
}
