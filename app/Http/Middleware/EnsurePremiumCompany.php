<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Premium-Gate Middleware.
 *
 * Checks if the authenticated user's company has an active premium subscription.
 * Must be applied AFTER EnsureHasCompany middleware (which sets ownerCompany on request).
 *
 * Non-premium users get a soft-lock view (premium upsell page) instead of a hard 403.
 */
class EnsurePremiumCompany
{
    public function handle(Request $request, Closure $next): Response
    {
        $company = $request->attributes->get('ownerCompany');

        if (! $company) {
            abort(403, 'Keine Firma zugeordnet.');
        }

        if (! $company->is_premium) {
            // Soft-Lock: Show premium upsell page instead of 403
            return response()->view('pages.dashboard.jobs.locked', [
                'company' => $company,
            ]);
        }

        return $next($request);
    }
}
