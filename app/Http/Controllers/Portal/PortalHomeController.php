<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Models\Portal\Category;
use App\Models\Portal\City;
use App\Models\Portal\Company;
use App\Models\Portal\FAQ;
use App\Models\Portal\Job;
use App\Models\Portal\Post;
use App\Models\Portal\Review;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class PortalHomeController extends Controller
{
    public function index(): View
    {
        $featuredCompanies = Cache::remember('portal.featured_companies', 300, fn () =>
            Company::active()
                ->with(['categories', 'city', 'media'])
                ->premium()
                ->whereHas('media', fn ($q) => $q->where('collection_name', 'gallery'))
                ->latest()
                ->take(6)
                ->get()
        );

        $latestCompanies = Cache::remember('portal.random_companies', 300, fn () =>
            $this->getRandomCompanies(6)
        );

        $categories = Cache::remember('portal.categories.home', 3600, fn () =>
            Category::roots()
                ->ordered()
                ->with(['children' => fn ($q) => $q->ordered()->withCount(['companies' => fn ($q2) => $q2->where('is_active', true)])])
                ->withCount(['companies' => fn ($q) => $q->where('is_active', true)])
                ->get()
        );

        $sortedCategories = $categories->sortByDesc('companies_count');
        $topCategories = $sortedCategories->take(3)->values();
        $restCategories = $sortedCategories->skip(3)->values();

        $popularCities = Cache::remember('portal.cities.hero', 3600, fn () =>
            City::withCount(['companies' => fn ($q) => $q->where('is_active', true)])
                ->having('companies_count', '>', 0)
                ->orderByDesc('companies_count')
                ->take(5)
                ->get()
        );

        $stats = Cache::remember('portal.stats', 900, fn () => [
            'totalCompanies' => Company::active()->count(),
            'totalCities' => Company::active()
                ->whereNotNull('city_id')
                ->distinct('city_id')
                ->count('city_id'),
            'avgRating' => round(
                (float) Company::active()
                    ->where('rating_count', '>', 0)
                    ->avg('rating'),
                1
            ),
            'totalReviews' => Review::approved()->count(),
        ]);

        $homeFaqs = Cache::remember('portal.home_faqs', 3600, fn () =>
            FAQ::active()->forPage('home')->ordered()->take(6)->get()
        );

        $latestPosts = Cache::remember('portal.latest_posts', 600, fn () =>
            Post::published()
                ->with(['category', 'media'])
                ->latest('published_at')
                ->take(4)
                ->get()
        );

        $latestJobs = Cache::remember('portal.latest_jobs', 600, fn () =>
            Job::active()
                ->published()
                ->with(['company', 'company.media', 'city'])
                ->latest('published_at')
                ->take(6)
                ->get()
        );

        return view('pages.home', [
            'featuredCompanies' => $featuredCompanies,
            'latestCompanies' => $latestCompanies,
            'latestJobs' => $latestJobs,
            'latestPosts' => $latestPosts,
            'homeFaqs' => $homeFaqs,
            'categories' => $categories,
            'popularCities' => $popularCities,
            'topCategories' => $topCategories,
            'restCategories' => $restCategories,
            'totalCompanies' => $stats['totalCompanies'],
            'totalCities' => $stats['totalCities'],
            'avgRating' => $stats['avgRating'],
            'totalReviews' => $stats['totalReviews'],
        ]);
    }

    /**
     * Random Companies: 1 Query mit inRandomOrder() statt 18 Offset-Queries.
     * Gecacht für 5 Min — danach neue Zufallsauswahl.
     */
    private function getRandomCompanies(int $count): \Illuminate\Database\Eloquent\Collection
    {
        return Company::active()
            ->whereHas('media', fn ($q) => $q->where('collection_name', 'gallery'))
            ->with(['categories', 'city', 'media'])
            ->inRandomOrder()
            ->take($count)
            ->get();
    }
}
