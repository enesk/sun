<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Models\Portal\Category;
use App\Models\Portal\Company;
use App\Models\Portal\Review;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class PortalHomeController extends Controller
{
    public function index(): View
    {
        $featuredCompanies = Company::active()
            ->with(['categories', 'city', 'media'])
            ->premium()
            ->latest()
            ->take(6)
            ->get();

        $latestCompanies = Company::active()
            ->with(['categories', 'city', 'media'])
            ->inRandomOrder()
            ->take(6)
            ->get();

        $categories = Category::roots()
            ->ordered()
            ->with(['children' => fn ($q) => $q->ordered()->withCount(['companies' => fn ($q2) => $q2->where('is_active', true)])])
            ->withCount(['companies' => fn ($q) => $q->where('is_active', true)])
            ->get();

        $popularCategories = $categories->sortByDesc('companies_count')->take(5)->values();

        // Top-3 Kategorien für visuelle Hierarchie (HP-3)
        $topCategories = $categories->sortByDesc('companies_count')->take(3)->values();
        $restCategories = $categories->sortByDesc('companies_count')->skip(3)->values();

        $totalCompanies = Company::active()->count();

        // Trust Bar: Anzahl Städte mit aktiven Firmen (ein einziger COUNT DISTINCT Query)
        $totalCities = Company::active()
            ->whereNotNull('city_id')
            ->distinct('city_id')
            ->count('city_id');

        // Trust Bar: Durchschnittsbewertung aller aktiven Firmen mit Bewertungen
        $avgRating = round(
            (float) Company::active()
                ->where('rating_count', '>', 0)
                ->avg('rating'),
            1
        );

        // Trust Bar: Gesamtzahl Bewertungen
        $totalReviews = Review::approved()->count();

        return view('pages.home', compact(
            'featuredCompanies',
            'latestCompanies',
            'categories',
            'popularCategories',
            'topCategories',
            'restCategories',
            'totalCompanies',
            'totalCities',
            'avgRating',
            'totalReviews',
        ));
    }
}
