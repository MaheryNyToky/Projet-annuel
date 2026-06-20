<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnsureAdminDashboard
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = Auth::user();

        if (!$user) {
            return redirect('/dashboard/login');
        }

        if (!in_array($user->role ?? null, ['admin', 'superadmin'], true)) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect('/dashboard/login')
                ->withErrors(['email' => 'Accès réservé aux comptes administrateur et superadmin.']);
        }

        return $next($request);
    }
}
