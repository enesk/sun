<?php

namespace App\Http\Middleware;

use App\Services\Premium\CompanyJobPostingService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Gate der Stellenanzeigen-Aktionen.
 *
 * Prueft die Freischaltung job_postings ueber den Entitlement-Service (#13),
 * nicht mehr das Alt-Flag is_premium. Must be applied AFTER EnsureHasCompany
 * middleware (which sets ownerCompany on request).
 *
 * Ohne Freischaltung gibt es statt 403 die gesperrte Ansicht (Upsell).
 */
class EnsurePremiumCompany
{
    public function handle(Request $request, Closure $next): Response
    {
        $company = $request->attributes->get('ownerCompany');

        if (! $company) {
            abort(403, 'Keine Firma zugeordnet.');
        }

        if (! app(CompanyJobPostingService::class)->canUse($company)) {
            // Soft-Lock: Show premium upsell page instead of 403
            return response()->view('pages.dashboard.jobs.locked', [
                'company' => $company,
            ]);
        }

        return $next($request);
    }
}
