<?php

namespace App\Http\Controllers\Portal;

use App\Enums\PremiumFeature;
use App\Http\Controllers\Controller;
use App\Models\Portal\Category;
use App\Models\Portal\City;
use App\Models\Portal\Company;
use App\Models\Portal\CompanyEvent;
use App\Models\Portal\Job;
use App\Services\CompanyListingFilters;
use App\Services\CompanyLocationSearch;
use App\Services\CompanyUrlService;
use App\Services\Premium\CompanyEntitlementService;
use App\Services\Premium\CompanyProfileContentService;
use App\Services\Premium\CompanyStatsRecorder;
use App\Services\Seo\SeoService;
use App\Services\TrackingService;
use App\Support\TenantCache;
use App\View\Components\AdSlot;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\View\View;

class CompanyController extends Controller
{
    public function __construct(
        private TrackingService $trackingService
    ) {}

    public function index(Request $request, SeoService $seo): View
    {
        $query = Company::active()
            ->with(['categories', 'city', 'media', 'openingHours']);

        // Freitext-Suche
        if ($request->filled('q')) {
            $query->search($request->q);
        }

        // Kategorie-Filter: JOIN statt whereHas für Performance
        $category = null;
        if ($request->filled('category')) {
            $category = Category::where('slug', $request->category)->first();
            if ($category) {
                $query->inCategory($category->id);
            }
        }

        // Stadt-Filter
        $city = null;
        if ($request->filled('city')) {
            $city = City::where('name', $request->city)->first();
            if ($city) {
                $query->inCity($city->id);
            }
        }

        // Ort oder PLZ, mit Umkreis sobald der Ort Koordinaten hat
        $location = new CompanyLocationSearch;
        if ($request->filled('ort')) {
            $location->apply($query, (string) $request->input('ort'), $request->integer('umkreis') ?: null);
        }

        // Premium-Filter
        if ($request->boolean('premium')) {
            $query->premium();
        }

        // Sterne, bewertet, jetzt geoeffnet
        app(CompanyListingFilters::class)->apply($query, $request);

        // Premium-Einträge immer oben, dann benutzerdefinierte Sortierung
        $sort = $request->get('sort', 'name');
        // Top-Platzierungen (#6) nur mit Stadt-Filter, sonst waeren sie portalweit
        $query->withFeaturedSlot($city?->id, $category?->id)
            ->orderFeaturedFirst($city?->id, $category?->id)
            ->orderByDesc('is_premium');

        $query = match ($sort) {
            'rating' => $query->orderByDesc('rating')->orderByDesc('rating_count'),
            'newest' => $query->latest(),
            'az' => $query->orderBy('name'),
            default => $query->orderBy('name'),
        };

        $companies = $query->paginate(18)->withQueryString();

        // Sidebar: gecacht (1h), ändert sich selten
        $categories = Cache::remember(TenantCache::key('portal.categories.sidebar'), 3600, fn () =>
            Category::roots()
                ->ordered()
                ->withCount(['companies' => fn ($q) => $q->where('is_active', true)])
                ->get()
        );

        // Cities Sidebar: TOP 50 statt unbounded, gecacht
        $cities = Cache::remember(TenantCache::key('portal.cities.sidebar'), 3600, fn () =>
            City::withCount(['companies' => fn ($q) => $q->where('is_active', true)])
                ->named()
                ->having('companies_count', '>', 0)
                ->orderByDesc('companies_count')
                ->limit(50)
                ->get()
        );

        $totalCompanies = Cache::remember(TenantCache::key('portal.stats.total'), 900, fn () =>
            Company::active()->count()
        );

        // Suchimpressionen tracken: nur bei aktiver Suche (nicht bei normalem Browsen)
        if ($request->filled('q') && $companies->isNotEmpty()) {
            $this->trackingService->trackSearchImpressions(
                $companies->pluck('id')->all(),
                $request->q,
                $request
            );
        }

        // Betriebsstatistik (#15): eine Impression je Karte, als ein Batch
        app(CompanyStatsRecorder::class)->listImpressions(
            $companies->pluck('id'),
            $request,
            $city?->id,
            CompanyEvent::SOURCE_SEARCH
        );

        // Robots + Canonical: Filter-URLs noindex, Canonical auf Stadtseite oder /firmen (#7)
        $seo->forCompanyListing($request, $city);

        return view('pages.companies.index', [
            ...compact('companies', 'categories', 'cities', 'totalCompanies', 'sort'),
            'searchOrigin' => $location->origin(),
            'searchRadius' => $location->radius(),
            'distanceByCity' => $location->distances(),
        ]);
    }

    public function show(string $companySlug): View|RedirectResponse
    {
        $company = Company::findByUrlSlug($companySlug);

        if (! $company || ! $company->is_active) {
            abort(404);
        }

        // 301 Redirect: falsches Pattern oder falscher Slug
        $redirect = CompanyUrlService::canonicalRedirect(
            $company,
            CompanyUrlService::PATTERN_ID_SLUG,
            companySlug: $companySlug
        );
        if ($redirect) {
            return redirect($redirect, 301);
        }

        return $this->renderCompanyShow($company);
    }

    public function showWithCity(string $citySlug, string $companySlug): View|RedirectResponse
    {
        $company = Company::findByUrlSlug($companySlug);

        if (! $company || ! $company->is_active) {
            abort(404);
        }

        // City eager-loaden falls noch nicht geladen (für Redirect-Check)
        if (! $company->relationLoaded('city')) {
            $company->load('city');
        }

        // 301 Redirect: falsches Pattern, falscher City-Slug oder falscher Company-Slug
        $redirect = CompanyUrlService::canonicalRedirect(
            $company,
            CompanyUrlService::PATTERN_CITY_ID_SLUG,
            citySlug: $citySlug,
            companySlug: $companySlug
        );
        if ($redirect) {
            return redirect($redirect, 301);
        }

        return $this->renderCompanyShow($company);
    }

    /**
     * Claim-Verifizierung: Dokument-Upload nach Claim-Request
     */
    public function claimVerification(string $slug): View
    {
        $company = Company::where('slug', $slug)
            ->where('is_active', true)
            ->with(['media'])
            ->firstOrFail();

        // Ohne Login kein Zugriff
        if (!auth()->check()) {
            return redirect()->route('login');
        }

        // User besitzt bereits eine Firma — keine zweite Verifizierung erlaubt
        $user = auth()->user();
        if (Company::where('user_id', $user->id)->exists()) {
            abort(403, 'Sie verwalten bereits ein Unternehmen. Eine zweite Übernahme ist nicht möglich.');
        }

        return view('pages.companies.verify-claim', compact('company'));
    }

    /**
     * PROF-1: Landingpage "Änderung vorschlagen"
     */
    public function suggestEdit(string $slug): View
    {
        $company = Company::where('slug', $slug)
            ->where('is_active', true)
            ->with(['categories', 'city', 'media', 'openingHours'])
            ->withCount('approvedReviews as reviews_count')
            ->firstOrFail();

        $breadcrumb = [
            ['label' => 'Home', 'url' => route('home')],
            ['label' => 'Firmen', 'url' => route('portal.companies.index')],
            ['label' => $company->name, 'url' => $company->portal_url],
            ['label' => 'Änderung vorschlagen'],
        ];

        return view('pages.companies.suggest-edit', compact('company', 'breadcrumb'));
    }

    private function renderCompanyShow(Company $company): View
    {
        // Page View Tracking via Request-Attribute (Middleware liest das nach Response)
        request()->attributes->set('tracked_company_id', $company->id);
        request()->attributes->set('tracked_company_city_id', $company->city_id);

        $company->load([
            'categories',
            'city',
            'media',
            'openingHours',
            'approvedReviews' => fn ($q) => $q->latest()->take(10),
        ]);

        // Werbefreies Profil (#8): keine Werbeplaetze, keine Wettbewerber
        $adFree = app(CompanyEntitlementService::class)->can($company, PremiumFeature::AdFree);

        if ($adFree) {
            AdSlot::suppress();
        }

        // Ähnliche Firmen: JOIN + LIMIT statt whereHas + ORDER BY RAND()
        $categoryIds = $company->categories->pluck('id')->all();
        $relatedCompanies = collect();

        if (! $adFree && !empty($categoryIds)) {
            $relatedCompanies = Company::active()
                ->where('companies.id', '!=', $company->id)
                ->join('category_company', 'companies.id', '=', 'category_company.company_id')
                ->whereIn('category_company.category_id', $categoryIds)
                ->select('companies.*')
                ->distinct()
                ->with(['categories', 'city', 'media'])
                ->limit(3)
                ->get();
        }

        // Offene Stellen (max 3)
        $companyJobs = Job::forCompany($company->id)
            ->active()
            ->published()
            ->with(['city'])
            ->latest('published_at')
            ->take(3)
            ->get();

        // Profil-Ausbau (#13): nur freigeschaltete Inhalte, Galerie auf das Plan-Limit gekuerzt
        $contents = app(CompanyProfileContentService::class);
        $profileGallery = $contents->visibleGallery($company);
        $profileVideo = $contents->video($company);
        $profileReferences = $contents->visibleReferences($company);
        $profileServices = $contents->visibleServices($company);
        $company->setRelation('services', $profileServices);

        // Schema.org LocalBusiness im <head> (#8)
        app(SeoService::class)->forCompanyProfile($company);

        // Breadcrumb
        $breadcrumb = [
            ['label' => 'Home', 'url' => route('home')],
            ['label' => 'Firmen', 'url' => route('portal.companies.index')],
        ];
        if ($company->categories->isNotEmpty()) {
            $cat = $company->categories->first();
            $breadcrumb[] = ['label' => $cat->name, 'url' => route('portal.categories.show', $cat->slug)];
        }
        $breadcrumb[] = ['label' => $company->name];

        return view('pages.companies.show', compact(
            'company',
            'relatedCompanies',
            'companyJobs',
            'breadcrumb',
            'profileGallery',
            'profileVideo',
            'profileReferences',
            'profileServices',
        ));
    }
}
