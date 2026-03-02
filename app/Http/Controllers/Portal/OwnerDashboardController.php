<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Models\Portal\Company;
use App\Models\Portal\Review;
use App\Models\Subscription;
use App\Services\StatisticsService;
use App\Services\SubscriptionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class OwnerDashboardController extends Controller
{
    private function getCompany(): Company
    {
        return Company::ownedBy(Auth::id())
            ->with(['categories', 'city', 'reviews', 'media'])
            ->firstOrFail();
    }

    public function index()
    {
        $company = $this->getCompany();

        // In-Memory Collection nutzen statt 3 extra COUNT-Queries
        $reviews = $company->reviews;

        // Tracking-KPIs aus StatisticsService (30d Default)
        $statsService = app(StatisticsService::class);
        $trackingSummary = $statsService->getCompanySummary($company->id, '30d');

        $stats = [
            'reviews_total' => $reviews->count(),
            'reviews_pending' => $reviews->where('moderation_status', 'pending')->count(),
            'reviews_approved' => $reviews->where('moderation_status', 'approved')->count(),
            'rating' => $company->rating,
            'rating_count' => $company->rating_count,
            'page_views' => $trackingSummary['page_views'],
            'page_views_change' => $trackingSummary['page_views_change'],
            'contact_clicks' => $trackingSummary['contact_clicks'],
            'contact_clicks_change' => $trackingSummary['contact_clicks_change'],
        ];

        $recentReviews = $company->reviews()
            ->approved()
            ->latest()
            ->take(3)
            ->get();

        $profileCompletion = $this->calculateProfileCompletion($company);

        return view('pages.dashboard.index', compact(
            'company', 'stats', 'recentReviews', 'profileCompletion'
        ));
    }

    public function edit()
    {
        $company = $this->getCompany();
        return view('pages.dashboard.edit', compact('company'));
    }

    public function reviews()
    {
        $company = $this->getCompany();

        $reviews = $company->reviews()
            ->latest()
            ->paginate(10);

        return view('pages.dashboard.reviews', compact('company', 'reviews'));
    }

    public function stats(Request $request)
    {
        $company = $this->getCompany();

        $statsService = app(StatisticsService::class);
        $period = in_array($request->input('period'), ['7d', '30d', '90d', '12m'], true)
            ? $request->input('period')
            : '30d';

        $summary = $statsService->getCompanySummary($company->id, $period);

        // Premium-User bekommen Trend, Referrer, Suchbegriffe, Wochen-Trend
        // Free-User sehen nur KPIs — keine unnötigen Queries
        $trend = collect();
        $referrers = collect();
        $searchQueries = collect();
        $weekly = collect();

        if ($company->is_premium) {
            $trend = $statsService->getDailyTrend($company->id, $period);
            $referrers = $statsService->getTopReferrers($company->id, $period);
            $searchQueries = $statsService->getTopSearchQueries($company->id, $period);
            $weekly = $statsService->getWeeklyTrend($company->id);
        }

        return view('pages.dashboard.stats', compact(
            'company', 'summary', 'trend', 'period',
            'referrers', 'searchQueries', 'weekly'
        ));
    }

    /**
     * JSON-API für Owner-Statistiken (AJAX/Livewire).
     *
     * GET /firmenprofil/statistiken/api
     * Query-Params: ?period=30d
     */
    public function statsApi(Request $request): JsonResponse
    {
        $company = $this->getCompany();

        $statsService = app(StatisticsService::class);
        $period = in_array($request->input('period'), ['7d', '30d', '90d', '12m'], true)
            ? $request->input('period')
            : '30d';

        $summary = $statsService->getCompanySummary($company->id, $period);
        $trend = $statsService->getDailyTrend($company->id, $period);

        // Premium-Gate: Free-User sehen nur Gesamtzahlen, keine Trends/Details
        if (! $company->is_premium) {
            return response()->json([
                'summary' => [
                    'page_views' => $summary['page_views'],
                    'contact_clicks' => $summary['contact_clicks'],
                    'search_impressions' => $summary['search_impressions'],
                    'period' => $period,
                    'is_premium' => false,
                ],
            ]);
        }

        $referrers = $statsService->getTopReferrers($company->id, $period);
        $searchQueries = $statsService->getTopSearchQueries($company->id, $period);
        $weekly = $statsService->getWeeklyTrend($company->id);

        return response()->json([
            'summary' => array_merge($summary, ['is_premium' => true]),
            'trend' => $trend->values(),
            'referrers' => $referrers->values(),
            'search_queries' => $searchQueries->values(),
            'weekly' => $weekly->values(),
        ]);
    }

    public function settings()
    {
        $company = $this->getCompany();
        return view('pages.dashboard.settings', compact('company'));
    }

    public function respondToReview(Request $request, int $review)
    {
        $company = $this->getCompany();

        // Manuell resolven statt Implicit Route Model Binding,
        // weil SubstituteBindings (Teil von 'web') VOR TENANCY_INITIALIZER läuft
        $review = Review::findOrFail($review);

        // Verify review belongs to this company
        abort_unless($review->company_id === $company->id, 403);

        // Premium gate
        abort_unless($company->is_premium, 403, 'Premium-Abo erforderlich.');

        // Only respond to approved reviews
        abort_unless($review->isApproved(), 422);

        $validated = $request->validate([
            'owner_response' => ['required', 'string', 'max:1000'],
        ]);

        $review->respondAsOwner($validated['owner_response']);

        return back()->with('success', 'Ihre Antwort wurde gespeichert.');
    }

    public function deleteReviewResponse(int $review)
    {
        $company = $this->getCompany();

        $review = Review::findOrFail($review);

        // Verify review belongs to this company
        abort_unless($review->company_id === $company->id, 403);

        // Only delete if there's actually a response
        abort_unless(! empty($review->owner_response), 422);

        $review->update([
            'owner_response' => null,
            'owner_response_at' => null,
        ]);

        return back()->with('success', 'Ihre Antwort wurde gelöscht.');
    }

    public function premium()
    {
        $company = $this->getCompany();

        // Aktive Subscription laden für Abo-Management
        $subscription = null;
        $canCancel = false;
        $canDiscardCancellation = false;

        $tenant = tenant();
        if ($tenant) {
            $subscription = Subscription::where('tenant_id', $tenant->id)
                ->with(['plan', 'currency', 'interval'])
                ->whereIn('status', ['active', 'past_due'])
                ->orderBy('updated_at', 'desc')
                ->first();

            if ($subscription) {
                $subscriptionService = app(SubscriptionService::class);
                $canCancel = $subscriptionService->canCancelSubscription($subscription);
                $canDiscardCancellation = $subscriptionService->canDiscardSubscriptionCancellation($subscription);
            }
        }

        return view('pages.dashboard.premium', compact(
            'company', 'subscription', 'canCancel', 'canDiscardCancellation'
        ));
    }

    private function calculateProfileCompletion(Company $company): array
    {
        $fields = [
            'name' => ['label' => 'Firmenname', 'filled' => ! empty($company->name)],
            'description' => ['label' => 'Beschreibung', 'filled' => ! empty($company->description)],
            'street' => ['label' => 'Adresse', 'filled' => ! empty($company->street)],
            'tel' => ['label' => 'Telefonnummer', 'filled' => ! empty($company->tel)],
            'email' => ['label' => 'E-Mail', 'filled' => ! empty($company->email)],
            'website' => ['label' => 'Website', 'filled' => ! empty($company->website)],
            'logo' => ['label' => 'Logo', 'filled' => $company->relationLoaded('media') ? $company->media->where('collection_name', 'logo')->isNotEmpty() : $company->getFirstMedia('logo') !== null],
            'categories' => ['label' => 'Kategorien', 'filled' => $company->categories->count() > 0],
        ];

        $filled = collect($fields)->where('filled', true)->count();
        $total = count($fields);
        $percentage = $total > 0 ? round(($filled / $total) * 100) : 0;

        return [
            'fields' => $fields,
            'filled' => $filled,
            'total' => $total,
            'percentage' => $percentage,
        ];
    }
}
