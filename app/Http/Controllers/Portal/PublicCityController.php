<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Models\Portal\Category;
use App\Models\Portal\City;
use App\Models\Portal\Company;
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
        $cities = Cache::remember('portal.cities.public.index.top20', 3600, fn () =>
            City::withCount(['companies' => fn ($q) => $q->where('is_active', true)])
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
    public function show(Request $request, string $slug): View
    {
        $city = City::where('slug', $slug)
            ->with('cityContent')
            ->withCount(['companies' => fn ($q) => $q->where('is_active', true)])
            ->firstOrFail();

        // Firmen in dieser Stadt mit Filtern
        $query = Company::active()
            ->where('city_id', $city->id)
            ->with(['categories', 'city', 'media']);

        // Freitext-Suche
        if ($request->filled('q')) {
            $query->search($request->q);
        }

        // Kategorie-Filter
        if ($request->filled('category')) {
            $category = Category::where('slug', $request->category)->first();
            if ($category) {
                $query->inCategory($category->id);
            }
        }

        // Premium oben, dann Sortierung
        $sort = $request->get('sort', 'name');
        $query->orderByDesc('is_premium');

        $query = match ($sort) {
            'rating' => $query->orderByDesc('rating')->orderByDesc('rating_count'),
            'newest' => $query->latest(),
            default => $query->orderBy('name'),
        };

        $companies = $query->paginate(18)->withQueryString();

        // Sidebar: Kategorien in dieser Stadt (gecacht)
        $categories = Cache::remember("portal.categories.city.{$city->id}", 3600, fn () =>
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
        $relatedCities = Cache::remember("portal.cities.related.{$city->id}", 3600, fn () =>
            City::where('administrative_area_level_1', $city->administrative_area_level_1)
                ->where('id', '!=', $city->id)
                ->withCount(['companies' => fn ($q) => $q->where('is_active', true)])
                ->having('companies_count', '>', 0)
                ->orderByDesc('companies_count')
                ->limit(12)
                ->get()
        );

        // SEO: Meta aus CityContent oder Fallback
        $portalName = tenant()?->name ?? config('app.name');
        $metaTitle = $city->cityContent?->meta_title
            ?: "Firmen in {$city->name} — {$portalName}";
        $metaDescription = $city->cityContent?->meta_description
            ?: "Finden Sie {$city->companies_count} Unternehmen in {$city->name}. Lokale Firmen, Handwerker und Dienstleister auf einen Blick.";

        return view('pages.cities.show', compact(
            'city',
            'companies',
            'categories',
            'relatedCities',
            'sort',
            'metaTitle',
            'metaDescription',
        ));
    }
}
