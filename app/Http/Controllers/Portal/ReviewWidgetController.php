<?php

declare(strict_types=1);

namespace App\Http\Controllers\Portal;

use App\Constants\TenantConfigConstants;
use App\Enums\PremiumFeature;
use App\Http\Controllers\Controller;
use App\Models\Portal\Company;
use App\Services\Premium\CompanyEntitlementService;
use App\Services\Premium\CompanyStatsRecorder;
use App\Services\Premium\ReviewInviteService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;

/**
 * Einbettbares Bewertungs-Widget (#12).
 *
 * /widget/{slug}.js schreibt ein iframe auf /widget/{slug} in die fremde Seite.
 * Beide Routen laufen ohne Session und CSRF, sind fuer GET per CORS offen und
 * duerfen gerahmt werden. Ohne Feature review_widget antworten beide mit 404.
 * Das HTML liegt eine Stunde im Cache; die Freischaltung wird vorher geprueft,
 * damit ein Downgrade nicht vom Cache verdeckt wird.
 */
class ReviewWidgetController extends Controller
{
    private const CACHE_TTL = 3600;

    private const LATEST_REVIEWS = 3;

    public function __construct(
        private readonly ReviewInviteService $invites,
        private readonly CompanyEntitlementService $entitlements,
    ) {}

    public function script(string $companySlug): Response
    {
        $company = $this->company($companySlug);

        $script = view('portal.widget.script', [
            'widgetUrl' => $this->invites->widgetUrl($company),
            'title' => __('portal.widget.iframe_title', ['firma' => $company->name]),
        ])->render();

        return $this->respond($script, 'application/javascript; charset=utf-8');
    }

    public function show(Request $request, string $companySlug, CompanyStatsRecorder $stats): Response
    {
        $company = $this->company($companySlug);

        $stats->widgetView((int) $company->id, $request);

        $tenant = tenant();
        $html = Cache::remember(
            "review-widget:{$tenant->getTenantKey()}:{$company->id}",
            self::CACHE_TTL,
            fn (): string => view('portal.widget.reviews', [
                'company' => $company,
                'reviews' => $company->approvedReviews()->latest()->limit(self::LATEST_REVIEWS)->get(),
                'portalName' => (string) $tenant->getAttribute('name'),
                'profileUrl' => "{$company->portal_url}#bewertungen",
                'color' => $this->brandColor((string) $tenant->getAttribute(TenantConfigConstants::PRIMARY_COLOR)),
            ])->render(),
        );

        return $this->respond($html, 'text/html; charset=utf-8')
            ->header('Content-Security-Policy', 'frame-ancestors *');
    }

    private function company(string $companySlug): Company
    {
        $company = $this->invites->resolveCompany($companySlug);

        abort_unless($this->entitlements->can($company, PremiumFeature::ReviewWidget), 404);

        return $company;
    }

    private function respond(string $content, string $contentType): Response
    {
        $response = response($content, 200, [
            'Content-Type' => $contentType,
            'Cache-Control' => 'public, max-age='.self::CACHE_TTL,
            'Access-Control-Allow-Origin' => '*',
            'Access-Control-Allow-Methods' => 'GET',
        ]);
        $response->headers->remove('X-Frame-Options');

        return $response;
    }

    /**
     * Markenfarbe wie im Theme sun-v2 (layouts/sun.blade.php): die projektweite
     * Voreinstellung und ungueltige Werte fallen auf die Theme-Farbe zurueck.
     * Nur Hex-Farben gelangen ins Inline-CSS.
     */
    private function brandColor(string $value): string
    {
        $projectDefault = (string) (TenantConfigConstants::DEFAULTS[TenantConfigConstants::PRIMARY_COLOR] ?? '');

        if (preg_match('/^#[0-9a-f]{3}([0-9a-f]{3})?$/i', $value) !== 1 || strcasecmp($value, $projectDefault) === 0) {
            return (string) config('themes.sun-v2.default_brand_color');
        }

        return $value;
    }
}
