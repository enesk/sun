<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Preview\PreviewContext;
use App\Preview\PreviewTokenService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Vorschau-Modus aus dem Cookie sun_preview (SUN-PV-001, Ticket #2).
 *
 * Steht nach der Tenancy-Initialisierung und vor {@see ResolveTheme}. Ohne
 * Cookie laeuft der Request unveraendert durch. Mit gueltigem Cookie wird
 * ein aktiver {@see PreviewContext} gebunden, die Theme-Aufloesung liefert
 * dann sun-v2, und die Antwort bekommt noindex und private, no-store. Ein
 * ungueltiges oder widerrufenes Cookie wird geloescht.
 */
class ResolvePreview
{
    public function __construct(
        private PreviewTokenService $previews,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $tenant = tenant();

        if (! $tenant || ! $request->hasCookie(PreviewTokenService::COOKIE)) {
            return $next($request);
        }

        $cookie = $this->previews->readCookie($request);

        if ($cookie === null || ! $this->previews->isValidForTenant($cookie['id'], $cookie['token'], $tenant)) {
            $response = $next($request);
            $response->headers->setCookie($this->previews->forgetCookie($request));

            return $response;
        }

        app()->instance(PreviewContext::class, new PreviewContext(true, $cookie['id']));

        $response = $next($request);
        $response->headers->set('X-Robots-Tag', 'noindex, nofollow');
        $response->headers->set('Cache-Control', 'private, no-store');

        return $response;
    }
}
