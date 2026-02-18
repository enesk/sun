<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Models\Portal\Category;
use App\Models\Portal\City;
use App\Models\Portal\Company;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\View\View;

class CategoryController extends Controller
{
    public function index(): View
    {
        $categories = Cache::remember('portal.categories.index', 3600, fn () =>
            Category::roots()
                ->ordered()
                ->withCount(['companies' => fn ($q) => $q->where('is_active', true)])
                ->with(['children' => function ($q) {
                    $q->ordered()->withCount(['companies' => fn ($q) => $q->where('is_active', true)]);
                }])
                ->get()
        );

        $totalCompanies = Cache::remember('portal.stats.total', 900, fn () =>
            Company::active()->count()
        );

        return view('pages.categories.index', compact(
            'categories',
            'totalCompanies',
        ));
    }

    public function show(Request $request, string $slug): View
    {
        $category = Category::where('slug', $slug)
            ->withCount(['companies' => fn ($q) => $q->where('is_active', true)])
            ->firstOrFail();

        $query = Company::active()
            ->inCategory($category->id)
            ->with(['categories', 'city', 'media']);

        // Freitext-Suche innerhalb der Kategorie
        if ($request->filled('q')) {
            $query->search($request->q);
        }

        // Stadt-Filter
        if ($request->filled('city')) {
            $city = City::where('name', $request->city)->first();
            if ($city) {
                $query->inCity($city->id);
            }
        }

        // Premium-Einträge immer oben, dann benutzerdefinierte Sortierung
        $sort = $request->get('sort', 'name');
        $query->orderByDesc('is_premium');

        $query = match ($sort) {
            'rating' => $query->orderByDesc('rating')->orderByDesc('rating_count'),
            'newest' => $query->latest(),
            default => $query->orderBy('name'),
        };

        $companies = $query->paginate(18)->withQueryString();

        // Sidebar: gecacht (1h)
        $allCategories = Cache::remember('portal.categories.sidebar', 3600, fn () =>
            Category::roots()
                ->ordered()
                ->withCount(['companies' => fn ($q) => $q->where('is_active', true)])
                ->get()
        );

        // Cities per Kategorie: JOIN statt doppelt-verschachtelter whereHas, gecacht + limitiert
        $cities = Cache::remember("portal.cities.category.{$category->id}", 3600, fn () =>
            City::select('cities.*')
                ->join('companies', 'cities.id', '=', 'companies.city_id')
                ->join('category_company', 'companies.id', '=', 'category_company.company_id')
                ->where('companies.is_active', true)
                ->where('category_company.category_id', $category->id)
                ->selectRaw('COUNT(DISTINCT companies.id) as companies_count')
                ->groupBy('cities.id')
                ->orderByDesc('companies_count')
                ->limit(50)
                ->get()
        );

        // Breadcrumb
        $breadcrumb = [
            ['label' => 'Home', 'url' => route('home')],
            ['label' => 'Kategorien', 'url' => route('portal.categories.index')],
            ['label' => $category->name],
        ];

        return view('pages.categories.show', compact(
            'category',
            'companies',
            'allCategories',
            'cities',
            'breadcrumb',
            'sort',
        ));
    }
}
