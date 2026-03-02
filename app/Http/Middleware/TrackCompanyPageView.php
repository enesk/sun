<?php

namespace App\Http\Middleware;

use App\Services\TrackingService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class TrackCompanyPageView
{
    public function __construct(
        private TrackingService $trackingService
    ) {}

    /**
     * Trackt Profilaufrufe NACH dem Response (terminate).
     * So wird die Response-Time nicht beeinflusst.
     */
    public function handle(Request $request, Closure $next): Response
    {
        return $next($request);
    }

    /**
     * Wird nach dem Senden der Response ausgeführt (terminable middleware).
     */
    public function terminate(Request $request, Response $response): void
    {
        // Nur erfolgreiche Responses tracken (kein 404, kein Redirect)
        if ($response->getStatusCode() !== 200) {
            return;
        }

        // AJAX/Livewire-Requests ignorieren
        if ($request->ajax() || $request->header('X-Livewire') !== null) {
            return;
        }

        // Company aus Route-Parameter extrahieren
        $companyId = $request->attributes->get('tracked_company_id');

        if ($companyId) {
            $this->trackingService->trackPageView($companyId, $request);
        }
    }
}
