<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserIsAdmin
{
    public function handle(Request $request, Closure $next): Response
    {
        // az admin panel auth útvonalait ne blokkoljuk (login, logout, jelszó stb.)
        $routeName = optional($request->route())->getName();
        if (is_string($routeName) && str_starts_with($routeName, 'filament.admin.auth.')) {
            return $next($request);
        }

        $user = $request->user();

        $isAdmin = false;
        if ($user) {
            $isAdmin = method_exists($user, 'isAdmin')
                ? (bool) $user->isAdmin()
                : (($user->role ?? null) === 'admin');
        }

        if (! $isAdmin) {
            return redirect()->route('filament.user.pages.dashboard');
        }

        return $next($request);
    }
}
