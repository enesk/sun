<?php

declare(strict_types=1);

namespace App\Ads\Http\Controllers;

use App\Ads\Enums\AdProviderType;
use App\Ads\Models\AdUnit;
use App\Ads\Providers\HtmlProvider;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;

/**
 * Zustellung eigener Werbemittel in den Sandbox-Rahmen
 * (docs/ad-slots.md 5.2 Regel 3, Ticket #4).
 *
 * Warum eine eigene Route und nicht `srcdoc`: `srcdoc` erbt die CSP des
 * Elterndokuments. Fremdes Markup liefe damit unter der Portal-CSP und
 * brauchte ein Nonce — genau das Modell, das der Sandbox-Rahmen ersetzen
 * soll. Mit eigener Route antwortet das Dokument mit eigener CSP:
 *
 *   Content-Security-Policy: sandbox …; frame-ancestors 'self'
 *
 * Dazu kommt die Sandbox auf dem iframe-Element selbst — zwei voneinander
 * unabhaengige Grenzen, beide ohne `allow-same-origin`.
 *
 * Die Route laeuft ohne Session und ohne CSRF: nichts hier liest den
 * angemeldeten Benutzer, und ein Session-Cookie im Rahmen waere das
 * Gegenteil des Ziels.
 */
class AdHtmlController extends Controller
{
    public function __invoke(AdUnit $adUnit): Response
    {
        // Nur aktive HTML-Werbemittel. Ein abgeschaltetes Werbemittel
        // verschwindet damit auch aus Rahmen, die noch im Browser stehen.
        abort_if(
            (string) $adUnit->provider !== AdProviderType::HTML->value || ! $adUnit->is_active,
            404,
        );

        $code = $adUnit->html();

        abort_if($code === null, 404);

        return response($this->document($code), 200, [
            'Content-Type' => 'text/html; charset=utf-8',
            // Opaker Origin plus nur die Portal-Domain als Rahmen-Eltern.
            'Content-Security-Policy' => 'sandbox '.HtmlProvider::SANDBOX."; frame-ancestors 'self'",
            'Cross-Origin-Resource-Policy' => 'same-site',
            'Referrer-Policy' => 'no-referrer',
            'X-Robots-Tag' => 'noindex, nofollow',
            // Der Abdruck in der URL (?v=) wechselt bei jeder Aenderung am
            // Markup, deshalb darf der Browser lange halten.
            'Cache-Control' => 'public, max-age=3600',
        ]);
    }

    /**
     * Rahmen um das fremde Markup. Bewusst minimal und ohne Theme: hier wird
     * nichts aus dem Portal geladen, was der Code erreichen koennte.
     */
    private function document(string $code): string
    {
        return <<<HTML
            <!DOCTYPE html>
            <html lang="de">
            <head>
            <meta charset="utf-8">
            <meta name="viewport" content="width=device-width, initial-scale=1">
            <meta name="robots" content="noindex,nofollow">
            <style>html,body{margin:0;padding:0;overflow:hidden}</style>
            </head>
            <body>{$code}</body>
            </html>
            HTML;
    }
}
