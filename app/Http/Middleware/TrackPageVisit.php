<?php

namespace App\Http\Middleware;

use App\Models\PageVisit;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class TrackPageVisit
{
    /**
     * Egyszerű, saját üzemeltetésű látogatásszámláló a publikus honlap oldalaihoz.
     * Nem használ sütit/session-t, csak egy hashelt IP-t tárol az egyedi látogatók
     * durva becsléséhez — a nyers IP-cím sosem kerül adatbázisba.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $userAgent = (string) $request->userAgent();
        $response = $next($request);

        $isBot = $userAgent === '' || preg_match('/bot|crawl|spider|slurp|preview/i', $userAgent);

        if (! $isBot && $response->getStatusCode() < 400) {
            PageVisit::create([
                'path' => $request->path(),
                'route_name' => $request->route()?->getName(),
                'ip_hash' => hash('sha256', $request->ip() . $userAgent),
                'user_agent' => mb_substr($userAgent, 0, 2000),
                'referrer' => mb_substr((string) $request->header('referer'), 0, 2000) ?: null,
            ]);
        }

        return $response;
    }
}
