<?php

namespace App\Http\Middleware;

use App\Models\Client;
use App\Models\Prestataire;
use App\Support\AccountStatus;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureAppAuth
{
    public function handle(Request $request, Closure $next, ?string $type = null): Response
    {
        $authId = $request->session()->get('auth_id');
        $authType = $request->session()->get('auth_type');

        if (! $authId) {
            return $request->expectsJson()
                ? response()->json(['ok' => false], 401)
                : redirect('/login');
        }

        if ($type && $authType !== $type) {
            return $request->expectsJson()
                ? response()->json(['ok' => false], 403)
                : redirect('/login');
        }

        $table = $authType === 'client' ? 'clients' : 'prestataires';
        $user = $authType === 'client'
            ? Client::query()->find($authId)
            : Prestataire::query()->find($authId);

        if (! $user || ! AccountStatus::isActive($user, $table)) {
            $request->session()->forget(['auth_id', 'auth_type', 'auth_name']);
            $msg = AccountStatus::blockMessage((string) ($user->statut ?? AccountStatus::SUPPRIME));

            return $request->expectsJson()
                ? response()->json(['ok' => false, 'message' => $msg], 403)
                : redirect('/login');
        }

        return $next($request);
    }
}
