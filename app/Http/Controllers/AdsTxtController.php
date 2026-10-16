<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Ads\Support\AdCache;
use App\Ads\Support\AdsTxtBuilder;
use Illuminate\Http\Response;

/**
 * `/ads.txt` je Portal-Domain (Ticket #10, docs/ad-slots.md 4.2).
 *
 * Der Inhalt kommt aus `ad_settings` der **Central**-DB
 * ({@see AdsTxtBuilder}) und nicht mehr aus `ad_settings.ads_txt_content` der
 * Tenant-DB — die wandert mit der Uebernahme (#15) nach
 * `ad_settings.ads_txt_extra`.
 *
 * 404 statt leerer Antwort, wenn nichts zu sagen ist: eine leere `ads.txt`
 * ist fuer Google die Aussage "diese Domain hat keinen autorisierten
 * Verkaeufer" und schaltet Anzeigen ab. Fehlt die Datei dagegen, gilt die
 * Domain als nicht erklaert — derselbe Zustand wie vor dem Umstieg.
 *
 * Die Route muss **vor** den Catch-all-Routen des Portals stehen
 * (`/{citySlug}/{companySlug}`, `/{companySlug}`), sonst verschluckt die
 * Stadtseite den Aufruf; sie steht deshalb oben in routes/tenant.php bei
 * robots.txt und llms.txt.
 */
class AdsTxtController extends Controller
{
    public function __construct(private readonly AdsTxtBuilder $builder) {}

    public function __invoke(): Response
    {
        $tenant = tenant();

        abort_if($tenant === null, 404);

        $content = $this->builder->forTenant($tenant);

        abort_if($content === '', 404);

        return response($content, 200, [
            'Content-Type' => 'text/plain; charset=UTF-8',
            // Dieselbe Frist wie im Serverspeicher — ein Crawler soll die
            // Datei nicht laenger halten als wir selbst.
            'Cache-Control' => 'public, max-age='.AdCache::ttl(),
            'X-Robots-Tag' => 'noindex',
        ]);
    }
}
