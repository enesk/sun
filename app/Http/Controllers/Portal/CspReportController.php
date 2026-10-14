<?php

declare(strict_types=1);

namespace App\Http\Controllers\Portal;

use App\Csp\Support\CspReportCollector;
use App\Csp\Support\CspReportPayload;
use App\Http\Controllers\Controller;
use App\Models\Tenant;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * POST /csp-bericht (#51): Sammelstelle fuer CSP-Verstoesse.
 *
 * Vorher setzte config/csp.php weder `report-uri` noch `report-to` — im Modus
 * `CSP_MODE=report` meldete der Browser nur in seine eigene Konsole, und die
 * 24-Stunden-Sichtung aus docs/turnstile-golive.md §1.5 konnte nichts
 * einsammeln. Die Adresse steht jetzt in beiden Direktiven und im Header
 * `Reporting-Endpoints` (App\Http\Middleware\ContentSecurityPolicy).
 *
 * Die Route laeuft ohne Tenancy-Initializer: `csp_reports` liegt zentral, und
 * mit Initializer waere die Adresse auf den zentralen Domains unbrauchbar (die
 * CSP wird auch dort gesetzt). Die Zuordnung zum Portal macht deshalb diese
 * Klasse ueber `tenants.domain` — derselbe Vergleich wie im
 * DomainTenantResolver, nur ohne die Tenant-DB zu oeffnen.
 *
 * Ohne Auth und ohne CSRF — der Browser schickt den Bericht ohne Session und
 * ohne Token, oft erst nach dem Verlassen der Seite. Abgesichert ist das
 * deshalb anders:
 *
 *   * Route-Throttle je IP (routes/tenant.php)
 *   * Rumpfgroesse hart begrenzt (`csp.report.max_body_bytes`)
 *   * hoechstens 20 Verstoesse je Anfrage (CspReportPayload)
 *   * hoechstens `csp.report.max_new_rows_per_hour` NEUE Zeilen je Portal
 *     und Stunde (CspReportCollector)
 *   * Portal ist immer der Host der Anfrage, nie ein Wert aus dem Rumpf
 *
 * Geantwortet wird immer 204, auch auf Unsinn: der Browser wertet die Antwort
 * nicht aus, und eine Fehlermeldung wuerde nur verraten, was durchkommt.
 * Abschaltbar ueber `CSP_REPORT_ENABLED=false` — dann gibt es die Route
 * weiter, sie schreibt aber nichts und die Direktiven bleiben leer.
 */
class CspReportController extends Controller
{
    public function __invoke(Request $request): Response
    {
        if (config('csp.report.enabled') !== true) {
            return response()->noContent();
        }

        $rumpf = $request->getContent();
        $grenze = (int) config('csp.report.max_body_bytes', 64_000);

        if ($rumpf === '' || strlen($rumpf) > $grenze) {
            return response()->noContent();
        }

        $violations = CspReportPayload::violations(json_decode($rumpf, true));

        if ($violations === []) {
            return response()->noContent();
        }

        // Host der Anfrage, nie ein Wert aus dem Rumpf. Ohne Treffer bleibt
        // tenant_id leer — das ist der Fall einer zentralen Domain.
        $portal = $request->getHost();

        $collector = new CspReportCollector(
            Tenant::query()->where('domain', $portal)->first()
        );

        $collector->collect($portal, $violations, $request->userAgent());

        return response()->noContent();
    }
}
