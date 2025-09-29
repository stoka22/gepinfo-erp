<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RedirectAdminFromUserPanel
{
    public function handle(Request $request, Closure $next): Response
    {
        // A user panel saját auth útvonalait ne blokkoljuk
        $routeName = optional($request->route())->getName();
        if (is_string($routeName) && str_starts_with($routeName, 'filament.user.auth.')) {
            return $next($request);
        }

        $user = $request->user();

        $isAdmin = false;
        if ($user) {
            // ha van isAdmin() metódusod, azt használjuk; különben role == 'admin'
            $isAdmin = method_exists($user, 'isAdmin')
                ? (bool) $user->isAdmin()
                : (($user->role ?? null) === 'admin');
        }

        if ($isAdmin) {
            return redirect()->route('filament.admin.pages.dashboard');
        }

        return $next($request);
    }
}
