<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Models\Portal\Category;
use App\Models\Portal\City;
use App\Models\Portal\Company;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CategoryController extends Controller
{
    public function index(): View
    {
        $categories = Category::roots()
            ->ordered()
            ->withCount(['companies' => fn ($q) => $q->where('is_active', true)])
            ->with(['children' => function ($q) {
                $q->ordered()->withCount(['companies' => fn ($q) => $q->where('is_active', true)]);
            }])
            ->get();

        $totalCompanies = Company::active()->count();

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
            ->with(['categories', 'city']);

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

        // Sidebar-Daten
        $allCategories = Category::roots()
            ->ordered()
            ->withCount(['companies' => fn ($q) => $q->where('is_active', true)])
            ->get();

        $cities = City::whereHas('companies', function ($q) use ($category) {
            $q->where('is_active', true)
                ->whereHas('categories', fn ($cq) => $cq->where('categories.id', $category->id));
        })
            ->withCount(['companies' => fn ($q) => $q->where('is_active', true)])
            ->orderByDesc('companies_count')
            ->get();

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
