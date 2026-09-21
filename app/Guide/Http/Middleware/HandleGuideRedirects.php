<?php

declare(strict_types=1);

namespace App\Guide\Http\Middleware;

use App\Guide\Models\Redirect;
use Closure;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * 301 fuer alte Ratgeber-Adressen (#18).
 *
 * Laeuft um die Ratgeber-Routen und greift erst, wenn die Route 404 liefert,
 * also bevor die 404-Seite ausgeliefert wird. Bestehende Seiten werden so nie
 * umgeleitet, und die Tabelle wird nur bei Fehlseiten abgefragt. Die
 * Query-Zeichenkette wandert mit.
 */
class HandleGuideRedirects
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if ($response->getStatusCode() !== Response::HTTP_NOT_FOUND || ! $request->isMethodSafe()) {
            return $response;
        }

        try {
            $target = Redirect::targetFor($request->getPathInfo());
        } catch (QueryException) {
            // Tenant-DB ohne guide_redirects (Migration noch nicht gelaufen)
            return $response;
        }

        // Nur Pfade auf dem eigenen Host; Altbestand mit absolutem Ziel bleibt 404.
        if ($target === null || ! str_starts_with($target, '/') || str_starts_with($target, '//')) {
            return $response;
        }

        $query = $request->getQueryString();

        return redirect()->to($target.($query !== null ? '?'.$query : ''), Response::HTTP_MOVED_PERMANENTLY);
    }
}
