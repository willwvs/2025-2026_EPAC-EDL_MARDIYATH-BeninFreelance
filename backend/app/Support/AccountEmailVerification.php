<?php

namespace App\Support;

use App\Models\Client;
use App\Models\Prestataire;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class AccountEmailVerification
{
    public static function issueToken(Model $user, string $table): string
    {
        $token = Str::random(64);
        $payload = ['email_verify_token' => $token];

        if (Schema::hasColumn($table, 'email_verify_expires_at')) {
            $payload['email_verify_expires_at'] = now()->addHours(24);
        }

        $user->update($payload);

        return $token;
    }

    public static function sendForClient(Client $client): void
    {
        if (! Schema::hasColumn('clients', 'email_verify_token')) {
            return;
        }

        $token = self::issueToken($client, 'clients');
        AppMailer::sendSignupVerification('client', $client->email, $client->prenom, $token);
    }

    public static function sendForPrestataire(Prestataire $prestataire): void
    {
        if (! Schema::hasColumn('prestataires', 'email_verify_token')) {
            return;
        }

        $token = self::issueToken($prestataire, 'prestataires');
        AppMailer::sendSignupVerification('prestataire', $prestataire->email, $prestataire->prenom, $token);
    }

    public static function isVerified(Model $user, string $table): bool
    {
        if (! Schema::hasColumn($table, 'email_verified_at')) {
            return true;
        }

        return $user->email_verified_at !== null;
    }
}
