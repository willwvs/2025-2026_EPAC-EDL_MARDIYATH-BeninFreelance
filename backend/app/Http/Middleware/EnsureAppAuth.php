<?php

namespace App\Http\Middleware;

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
            return redirect('/login');
        }

        if ($type && $authType !== $type) {
            return redirect('/login');
        }

        return $next($request);
    }
}
