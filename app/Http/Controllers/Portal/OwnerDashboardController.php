<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Models\Portal\Company;
use App\Models\Portal\Review;
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
        $stats = [
            'reviews_total' => $reviews->count(),
            'reviews_pending' => $reviews->where('moderation_status', 'pending')->count(),
            'reviews_approved' => $reviews->where('moderation_status', 'approved')->count(),
            'rating' => $company->rating,
            'rating_count' => $company->rating_count,
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

    public function stats()
    {
        $company = $this->getCompany();
        return view('pages.dashboard.stats', compact('company'));
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
        return view('pages.dashboard.premium', compact('company'));
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
