<?php

declare(strict_types=1);

namespace App\Guide\Http\Middleware;

use App\Guide\Services\ContentTenantContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Setzt den Tenancy-Kontext des Content-Panels aus der Session-Auswahl
 * des Tenant-Switchers (#4).
 *
 * Laeuft hinter Authenticate, weil die Auswahl gegen die Portalzuordnung
 * des angemeldeten Administrators geprueft wird. Ist nichts oder
 * "Alle Portale" gewaehlt, bleibt die Anfrage im Central-Kontext.
 *
 * Der Kontext wird nach der Anfrage wieder beendet, damit ein Queue-Worker
 * oder ein nachfolgender Request keine fremde Tenant-Verbindung erbt.
 */
class InitializeContentTenant
{
    public function __construct(private readonly ContentTenantContext $context) {}

    public function handle(Request $request, Closure $next): Response
    {
        $this->context->apply();

        return $next($request);
    }

    public function terminate(Request $request, Response $response): void
    {
        if (tenancy()->initialized) {
            tenancy()->end();
        }
    }
}
