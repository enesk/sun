<?php

declare(strict_types=1);

namespace App\Ads\Http\Controllers;

use App\Ads\Enums\AdProviderType;
use App\Ads\Models\AdUnit;
use App\Ads\Providers\HtmlProvider;
use App\Ads\Support\AdsAdminAccess;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;

/**
 * Vorschau eines Werbemittels im Admin-Panel (Ticket #10).
 *
 * Getrennt von der Zustellroute des Portals
 * ({@see AdHtmlController}, `portal.ads.html`): die laeuft nur auf
 * Portal-Domains, liefert ausschliesslich aktive Werbemittel und ist
 * oeffentlich. Die Vorschau liegt auf der zentralen Domain, ist auf Betreiber
 * beschraenkt und zeigt auch Entwuerfe, abgeschaltete und archivierte
 * Werbemittel — sonst waere genau das nicht pruefbar, was noch nicht laeuft.
 *
 * Gleich bleibt die Grenze: fremdes Markup kommt **nie** in das DOM des
 * Panels, sondern nur in einen `<iframe sandbox>` ohne `allow-same-origin`,
 * und die Antwort setzt ihre eigene CSP. `srcdoc` waere der kurze Weg, erbt
 * aber die CSP des Panels (docs/ad-slots.md 5.2, Regel 3).
 *
 * Nur `provider = html` bekommt einen Rahmen. AdSense wird in der Vorschau
 * nicht geladen — eine Impression auf einer Verwaltungsseite ist bei Google
 * ein ungueltiger Vorfall; dort steht der Platzhalter mit der
 * Anzeigenblock-ID, gerendert im Panel selbst.
 */
class AdUnitPreviewController extends Controller
{
    public function __invoke(AdUnit $adUnit): Response
    {
        abort_unless(AdsAdminAccess::allowed(), 403);

        abort_if((string) $adUnit->provider !== AdProviderType::HTML->value, 404);

        $code = $adUnit->html();

        abort_if($code === null, 404);

        return response($this->document($code), 200, [
            'Content-Type' => 'text/html; charset=utf-8',
            'Content-Security-Policy' => 'sandbox '.HtmlProvider::SANDBOX."; frame-ancestors 'self'",
            'Cross-Origin-Resource-Policy' => 'same-site',
            'Referrer-Policy' => 'no-referrer',
            'X-Robots-Tag' => 'noindex, nofollow',
            // Die Vorschau soll den gerade gespeicherten Stand zeigen.
            'Cache-Control' => 'no-store, private',
        ]);
    }

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
