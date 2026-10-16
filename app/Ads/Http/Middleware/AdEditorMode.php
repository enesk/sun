<?php

declare(strict_types=1);

namespace App\Ads\Http\Middleware;

use App\Ads\EditorToken;
use App\Ads\Support\AdEditorMode as EditorFlag;
use App\View\Components\AdSlot as LegacyAdSlot;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Editor-Modus einer Portalseite (SUN-AD-005, Ticket #6, docs/ad-slots.md 6).
 *
 * Haengt an der Portal-Routengruppe (routes/tenant.php) und tut fuer jeden
 * normalen Besucher genau nichts — ohne Token in der Adresse laeuft die Seite
 * unveraendert durch. Mit gueltigem Token:
 *
 *  1. {@see EditorFlag::enable()} — die Komponente <x-sun.ad-slot> rendert
 *     dann jeden Katalog-Anker als Rahmen, auch den unbelegten, und der
 *     Resolver umgeht den Cache.
 *  2. {@see LegacyAdSlot::suppress()} — das Altsystem (`ad_slots` je
 *     Tenant-DB, Auto Ads, Sticky-Banner) liefert in der Vorschau nichts aus.
 *     Sonst stuende echtes AdSense im Quelltext, und ein Impression auf einer
 *     Vorschauseite ist bei Google ein ungueltiger Vorfall.
 *  3. `X-Robots-Tag: noindex, nofollow` auf der Antwort, dazu `<meta robots>`
 *     im Layout. Eine signierte Adresse ist nicht geheim.
 *
 * Ein abgelaufener oder manipulierter Token ergibt **keine** Fehlerseite,
 * sondern die normale Seite: die Adresse landet im Verlauf und in Lesezeichen
 * (Ticket #6, Akzeptanz 5). Abgewiesen wird nur ein schreibender Zugriff mit
 * Token — der Modus ist lesend, gespeichert wird im Panel.
 */
class AdEditorMode
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! EditorToken::present($request)) {
            return $next($request);
        }

        if (! $request->isMethodSafe()) {
            abort(405, 'Der Editor-Modus ist lesend.');
        }

        if (! EditorToken::valid($request)) {
            return $next($request);
        }

        EditorFlag::enable();
        LegacyAdSlot::suppress();

        $response = $next($request);
        $response->headers->set('X-Robots-Tag', 'noindex, nofollow');

        return $response;
    }
}
