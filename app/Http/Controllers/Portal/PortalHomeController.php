<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Models\Portal\Category;
use App\Models\Portal\City;
use App\Models\Portal\Company;
use App\Models\Portal\FAQ;
use App\Models\Portal\Job;
use App\Models\Portal\Review;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class PortalHomeController extends Controller
{
    public function index(): View
    {
        $featuredCompanies = Company::active()
            ->with(['categories', 'city', 'media'])
            ->premium()
            ->whereHas('media', fn ($q) => $q->where('collection_name', 'gallery'))
            ->latest()
            ->take(6)
            ->get();

        // Random-Offset statt ORDER BY RAND() — O(1) statt O(n)
        $latestCompanies = $this->getRandomCompanies(6);

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

        // Top 5 Städte nach Firmenanzahl für Hero-Animation
        $popularCities = Cache::remember('portal.cities.hero', 3600, fn () =>
            City::withCount(['companies' => fn ($q) => $q->where('is_active', true)])
                ->having('companies_count', '>', 0)
                ->orderByDesc('companies_count')
                ->take(5)
                ->get()
        );

        // Trust Bar Stats: 4 Queries → 1 gecachter Block (15 Min)
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

        // FAQs für Startseite (max 6)
        $homeFaqs = FAQ::active()->forPage('home')->ordered()->take(6)->get();

        // Aktuelle Stellenanzeigen (optional, nur wenn Jobs existieren)
        $latestJobs = Job::active()
            ->published()
            ->with(['company', 'company.media', 'city'])
            ->latest('published_at')
            ->take(6)
            ->get();

        return view('pages.home', [
            'featuredCompanies' => $featuredCompanies,
            'latestCompanies' => $latestCompanies,
            'latestJobs' => $latestJobs,
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
     * Random Companies per Offset statt ORDER BY RAND().
     * ORDER BY RAND() sortiert ALLE Zeilen (O(n log n)) — bei 12k+ Rows ~50ms+.
     * Offset-Methode: 1x COUNT + 6x SELECT mit OFFSET = O(6).
     */
    private function getRandomCompanies(int $count): \Illuminate\Database\Eloquent\Collection
    {
        $baseQuery = Company::active()->whereHas('media', fn ($q) => $q->where('collection_name', 'gallery'));
        $total = $baseQuery->count();

        if ($total === 0) {
            return new \Illuminate\Database\Eloquent\Collection();
        }

        $ids = collect();
        $maxAttempts = $count * 3;
        $attempts = 0;

        while ($ids->count() < $count && $attempts < $maxAttempts) {
            $offset = random_int(0, max(0, $total - 1));
            $id = (clone $baseQuery)->select('companies.id')->skip($offset)->first()?->id;
            if ($id && !$ids->contains($id)) {
                $ids->push($id);
            }
            $attempts++;
        }

        if ($ids->isEmpty()) {
            return new \Illuminate\Database\Eloquent\Collection();
        }

        return Company::whereIn('id', $ids->all())
            ->with(['categories', 'city', 'media'])
            ->get();
    }
}
