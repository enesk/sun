<?php

namespace App\Http\Controllers\Portal;

use App\Enums\ModerationStatus;
use App\Enums\PremiumFeature;
use App\Http\Controllers\Controller;
use App\Models\Portal\Company;
use App\Models\Portal\Review;
use App\Models\Subscription;
use App\Services\Premium\CompanyEntitlementService;
use App\Services\Premium\CompanyStatsRecorder;
use App\Services\Premium\LeadQuotaService;
use App\Services\Premium\ReviewInviteService;
use App\Services\StatisticsService;
use App\Services\SubscriptionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;

class OwnerDashboardController extends Controller
{
    public const UPSELL_BANNER_SESSION_KEY = 'premium.upsell_banner_dismissed';

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
            'reviews_pending' => $reviews->whereIn('moderation_status', ModerationStatus::openValues())->count(),
            'reviews_approved' => $reviews->where('moderation_status', 'approved')->count(),
            'rating' => $company->rating,
            'rating_count' => $company->rating_count,
            'page_views' => $trackingSummary['page_views'],
            'page_views_change' => $trackingSummary['page_views_change'],
            'contact_clicks' => $trackingSummary['contact_clicks'],
            'contact_clicks_change' => $trackingSummary['contact_clicks_change'],
            'search_impressions' => $trackingSummary['search_impressions'],
        ];

        $recentReviews = $company->reviews()
            ->approved()
            ->latest()
            ->take(3)
            ->get();

        $profileCompletion = $this->calculateProfileCompletion($company);

        $leadQuota = app(LeadQuotaService::class)->summary($company);

        return view('pages.dashboard.index', compact(
            'company', 'stats', 'recentReviews', 'profileCompletion', 'leadQuota'
        ));
    }

    public function edit()
    {
        $company = $this->getCompany();
        return view('pages.dashboard.edit', compact('company'));
    }

    public function reviews(Request $request)
    {
        $company = $this->getCompany();

        $filter = in_array($request->input('filter'), ['published', 'pending', 'unanswered'], true)
            ? $request->input('filter')
            : 'all';

        $reviews = $company->reviews()
            ->when($filter === 'published', fn ($query) => $query->approved())
            ->when($filter === 'pending', fn ($query) => $query->whereIn('moderation_status', ModerationStatus::openValues()))
            ->when($filter === 'unanswered', fn ($query) => $query->approved()->where(
                fn ($query) => $query->whereNull('owner_response')->orWhere('owner_response', '')
            ))
            ->latest()
            ->paginate(10)
            ->withQueryString();

        // Zaehler fuer Filter, Zusammenfassung und Premium-Karte aus der geladenen Collection
        $approved = $company->reviews->where('moderation_status', Review::STATUS_APPROVED);
        $counts = [
            'all' => $company->reviews->count(),
            'published' => $approved->count(),
            'pending' => $company->reviews->whereIn('moderation_status', ModerationStatus::openValues())->count(),
            'unanswered' => $approved->filter(fn (Review $review) => empty($review->owner_response))->count(),
        ];
        $distribution = collect([5, 4, 3, 2, 1])
            ->mapWithKeys(fn (int $stars) => [$stars => $approved->filter(fn (Review $review) => (int) round((float) $review->rating) === $stars)->count()])
            ->all();

        $canReply = app(CompanyEntitlementService::class)->can($company, PremiumFeature::ReviewReplies);
        $reviewLink = app(ReviewInviteService::class)->inviteUrl($company);

        return view('pages.dashboard.reviews', compact('company', 'reviews', 'filter', 'counts', 'distribution', 'canReply', 'reviewLink'));
    }

    /**
     * QR-Code zum Bewertungslink als SVG-Download (#12).
     */
    public function reviewQrCode(ReviewInviteService $invites): Response
    {
        $company = $this->getCompany();

        return response($invites->qrSvg($company), 200, [
            'Content-Type' => 'image/svg+xml',
            'Content-Disposition' => 'attachment; filename="'.$invites->qrFilename($company).'"',
            'Cache-Control' => 'private, no-store',
        ]);
    }

    public function stats(Request $request)
    {
        $company = $this->getCompany();

        $statsService = app(StatisticsService::class);
        $period = in_array($request->input('period'), ['7d', '30d', '90d', '12m'], true)
            ? $request->input('period')
            : '30d';

        $summary = $statsService->getCompanySummary($company->id, $period);

        // Den Tagesverlauf sehen alle. Premium-User bekommen zusaetzlich Referrer,
        // Suchbegriffe und Wochen-Trend — Free-User keine unnötigen Queries
        $trend = $statsService->getDailyTrend($company->id, $period);
        $referrers = collect();
        $searchQueries = collect();
        $weekly = collect();

        if ($company->is_premium) {
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

    /**
     * Speichert oder aendert die eine Antwort des Betriebs (#12, Feature review_replies).
     */
    public function respondToReview(Request $request, int $review, CompanyEntitlementService $entitlements, CompanyStatsRecorder $stats)
    {
        $company = $this->getCompany();

        // Manuell resolven statt Implicit Route Model Binding,
        // weil SubstituteBindings (Teil von 'web') VOR TENANCY_INITIALIZER läuft
        $review = Review::findOrFail($review);

        // Verify review belongs to this company
        abort_unless($review->company_id === $company->id, 403);

        abort_unless($entitlements->can($company, PremiumFeature::ReviewReplies), 403, 'Antworten auf Bewertungen sind in Ihrem Paket nicht enthalten.');

        // Only respond to approved reviews
        abort_unless($review->isApproved(), 422);

        $validated = $request->validate([
            'owner_response' => ['required', 'string', 'max:1000'],
        ]);

        $isNewReply = empty($review->owner_response);

        $review->respondAsOwner($validated['owner_response']);

        if ($isNewReply) {
            $stats->reviewReply((int) $company->id, $request);
        }

        return back()->with('success', __('portal.owner.reviews.reply_saved'));
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

        return back()->with('success', __('portal.owner.reviews.reply_deleted'));
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

    /**
     * Mein Plan (#17): Inhalt liefert die Livewire-Komponente
     * portal.company.dashboard.plan.
     */
    public function plan()
    {
        $company = $this->getCompany();

        return view('pages.dashboard.plan', compact('company'));
    }

    /**
     * Upsell-Banner fuer Free-Betriebe bis zum Ende der Session ausblenden (#17).
     */
    public function dismissUpsellBanner(Request $request)
    {
        $request->session()->put(self::UPSELL_BANNER_SESSION_KEY, true);

        return back();
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
