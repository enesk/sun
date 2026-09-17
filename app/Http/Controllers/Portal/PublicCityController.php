<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Models\Portal\Category;
use App\Models\Portal\City;
use App\Models\Portal\Company;
use App\Models\Portal\CompanyEvent;
use App\Services\CompanyListingFilters;
use App\Services\Premium\CompanyStatsRecorder;
use App\Services\Content\CityContentResolver;
use App\Services\Seo\CityMetaTemplates;
use App\Services\Seo\SeoService;
use App\Themes\ThemeManager;
use App\Support\TenantCache;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\View\View;

class PublicCityController extends Controller
{
    /**
     * Städte-Übersichtsseite: Alle Städte mit Firmenanzahl, gruppiert nach Bundesland.
     */
    public function index(): View
    {
        $cities = Cache::remember(TenantCache::key('portal.cities.public.index.top20'), 3600, fn () =>
            City::withCount(['companies' => fn ($q) => $q->where('is_active', true)])
                ->named()
                ->having('companies_count', '>', 0)
                ->orderByDesc('companies_count')
                ->limit(20)
                ->get()
        );

        $totalCities = $cities->count();
        $totalCompanies = $cities->sum('companies_count');

        return view('pages.cities.index', compact(
            'cities',
            'totalCities',
            'totalCompanies',
        ));
    }

    /**
     * Stadt-Detailseite: Introtext + Firmenverzeichnis gefiltert nach Stadt.
     */
    public function show(Request $request, SeoService $seo, string $slug): View
    {
        $city = City::where('slug', $slug)
            ->named()
            ->with('cityContent')
            ->firstOrFail();

        // Aktive Betriebe aus dem 6-h-Cache des SEO-Templates statt withCount je Anfrage (#10)
        $cityMeta = app(CityMetaTemplates::class);
        $city->setAttribute('companies_count', $cityMeta->activeCompanyCount($city));

        // Firmen in dieser Stadt mit Filtern
        $query = Company::active()
            ->where('city_id', $city->id)
            ->with(['categories', 'city', 'media', 'openingHours']);

        // Freitext-Suche
        if ($request->filled('q')) {
            $query->search($request->q);
        }

        // Kategorie-Filter
        $category = null;
        if ($request->filled('category')) {
            $category = Category::where('slug', $request->category)->first();
            if ($category) {
                $query->inCategory($category->id);
            }
        }

        // Sterne, bewertet, jetzt geoeffnet (wie in der Suche)
        app(CompanyListingFilters::class)->apply($query, $request);

        // Premium oben, dann Sortierung
        // Voreinstellung je Theme (config/themes/<slug>.php -> city.default_sort), sonst Name
        $themeSlug = app(ThemeManager::class)->active()?->slug;
        $sort = $request->get('sort', config("themes.{$themeSlug}.city.default_sort", 'name'));
        // Top-Platzierungen (#6) vor Premium
        $query->withFeaturedSlot($city->id, $category?->id)
            ->orderFeaturedFirst($city->id, $category?->id)
            ->orderByDesc('is_premium');

        $query = match ($sort) {
            'rating' => $query->orderByDesc('rating')->orderByDesc('rating_count'),
            'newest' => $query->latest(),
            default => $query->orderBy('name'),
        };

        $companies = $query->paginate(18)->withQueryString();

        // Sidebar: Kategorien in dieser Stadt (gecacht)
        $categories = Cache::remember(TenantCache::key("portal.categories.city.{$city->id}"), 3600, fn () =>
            Category::select('categories.id', 'categories.name', 'categories.slug')
                ->join('category_company', 'categories.id', '=', 'category_company.category_id')
                ->join('companies', 'companies.id', '=', 'category_company.company_id')
                ->where('companies.is_active', true)
                ->where('companies.city_id', $city->id)
                ->whereNull('categories.parent_id')
                ->selectRaw('COUNT(DISTINCT companies.id) as companies_count')
                ->groupBy('categories.id', 'categories.name', 'categories.slug')
                ->orderByDesc('companies_count')
                ->limit(30)
                ->get()
        );

        // Verwandte Städte (gleicher Bundesland, gecacht)
        $relatedCities = Cache::remember(TenantCache::key("portal.cities.related.{$city->id}"), 3600, fn () =>
            City::where('administrative_area_level_1', $city->administrative_area_level_1)
                ->named()
                ->where('id', '!=', $city->id)
                ->withCount(['companies' => fn ($q) => $q->where('is_active', true)])
                ->having('companies_count', '>', 0)
                ->orderByDesc('companies_count')
                ->limit(12)
                ->get()
        );

        // SEO: Title/Description/H1 aus dem Tenant-Template, Overrides aus CityContent haben Vorrang (#10)
        ['title' => $metaTitle, 'description' => $metaDescription, 'heading' => $cityHeading] = $seo->cityMeta($city);

        // Local Hub (Intro, Stadtteile, FAQ) nur auf der indexierbaren Seite 1 (#12)
        $localHub = SeoService::isIndexableCityPage($request)
            ? app(CityContentResolver::class)->forCity($city)
            : null;

        // Robots + Canonical: ab Seite 2 oder mit Filtern noindex, Canonical auf Seite 1 (#7);
        // auf Seite 1 ohne Parameter zusaetzlich die ItemList der sichtbaren Betriebe (#9)
        // und das FAQPage-Schema aus denselben Fragen wie im HTML (#12)
        $seo->forCityPage($request, $city, $companies, $localHub);

        // Betriebsstatistik (#15): eine Impression je Karte, als ein Batch
        app(CompanyStatsRecorder::class)->listImpressions($companies->pluck('id'), $request, $city->id, CompanyEvent::SOURCE_CITY);

        return view('pages.cities.show', compact(
            'city',
            'companies',
            'categories',
            'relatedCities',
            'sort',
            'metaTitle',
            'metaDescription',
            'cityHeading',
            'localHub',
        ));
    }
}
