<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Models\Portal\Category;
use App\Models\Portal\City;
use App\Models\Portal\Company;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CompanyController extends Controller
{
    public function index(Request $request): View
    {
        $query = Company::active()
            ->with(['categories', 'city']);

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

        // Sidebar-Daten
        $categories = Category::roots()
            ->ordered()
            ->withCount(['companies' => fn ($q) => $q->where('is_active', true)])
            ->get();

        $cities = City::withCount(['companies' => fn ($q) => $q->where('is_active', true)])
            ->having('companies_count', '>', 0)
            ->orderByDesc('companies_count')
            ->get();

        $totalCompanies = Company::active()->count();

        return view('pages.companies.index', compact(
            'companies',
            'categories',
            'cities',
            'totalCompanies',
            'sort',
        ));
    }

    public function show(string $slug): View
    {
        $company = Company::active()
            ->where('slug', $slug)
            ->with([
                'categories',
                'city',
                'openingHours',
                'approvedReviews' => fn ($q) => $q->latest()->take(10),
            ])
            ->firstOrFail();

        // Ähnliche Firmen (gleiche Kategorie)
        $relatedCompanies = Company::active()
            ->where('id', '!=', $company->id)
            ->whereHas('categories', function ($q) use ($company) {
                $q->whereIn('categories.id', $company->categories->pluck('id'));
            })
            ->with(['categories', 'city'])
            ->inRandomOrder()
            ->take(3)
            ->get();

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
