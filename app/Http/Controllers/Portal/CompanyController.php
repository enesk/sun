<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Models\Portal\Category;
use App\Models\Portal\City;
use App\Models\Portal\Company;
use App\Services\CompanyUrlService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\View\View;

class CompanyController extends Controller
{
    public function index(Request $request): View
    {
        $query = Company::active()
            ->with(['categories', 'city', 'media']);

        // Freitext-Suche
        if ($request->filled('q')) {
            $query->search($request->q);
        }

        // Kategorie-Filter: JOIN statt whereHas für Performance
        if ($request->filled('category')) {
            $category = Category::where('slug', $request->category)->first();
            if ($category) {
                $query->inCategory($category->id);
            }
        }

        // Stadt-Filter
        if ($request->filled('city')) {
            $city = City::where('name', $request->city)->first();
            if ($city) {
                $query->inCity($city->id);
            }
        }

        // Premium-Filter
        if ($request->boolean('premium')) {
            $query->premium();
        }

        // Premium-Einträge immer oben, dann benutzerdefinierte Sortierung
        $sort = $request->get('sort', 'name');
        $query->orderByDesc('is_premium');

        $query = match ($sort) {
            'rating' => $query->orderByDesc('rating')->orderByDesc('rating_count'),
            'newest' => $query->latest(),
            'az' => $query->orderBy('name'),
            default => $query->orderBy('name'),
        };

        $companies = $query->paginate(18)->withQueryString();

        // Sidebar: gecacht (1h), ändert sich selten
        $categories = Cache::remember('portal.categories.sidebar', 3600, fn () =>
            Category::roots()
                ->ordered()
                ->withCount(['companies' => fn ($q) => $q->where('is_active', true)])
                ->get()
        );

        // Cities Sidebar: TOP 50 statt unbounded, gecacht
        $cities = Cache::remember('portal.cities.sidebar', 3600, fn () =>
            City::withCount(['companies' => fn ($q) => $q->where('is_active', true)])
                ->having('companies_count', '>', 0)
                ->orderByDesc('companies_count')
                ->limit(50)
                ->get()
        );

        $totalCompanies = Cache::remember('portal.stats.total', 900, fn () =>
            Company::active()->count()
        );

        return view('pages.companies.index', compact(
            'companies',
            'categories',
            'cities',
            'totalCompanies',
            'sort',
        ));
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
     * PROF-1: Landingpage "Änderung vorschlagen"
     */
    public function suggestEdit(string $slug): View
    {
        $company = Company::where('slug', $slug)
            ->where('is_active', true)
            ->with(['categories', 'city', 'media'])
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
        $company->load([
            'categories',
            'city',
            'media',
            'openingHours',
            'approvedReviews' => fn ($q) => $q->latest()->take(10),
        ]);

        // Ähnliche Firmen: JOIN + LIMIT statt whereHas + ORDER BY RAND()
        $categoryIds = $company->categories->pluck('id')->all();
        $relatedCompanies = collect();

        if (!empty($categoryIds)) {
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
            'breadcrumb',
        ));
    }
}
