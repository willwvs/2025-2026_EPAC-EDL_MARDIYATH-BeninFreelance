<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureAdmin
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->session()->get('admin_ok')) {
            if ($request->expectsJson() || str_starts_with($request->path(), 'admin/api')) {
                return response()->json(['ok' => false, 'message' => 'Session admin expirée.'], 401);
            }

            return redirect('/admin/login');
        }

        return $next($request);
    }
}
