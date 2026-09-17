<?php

namespace App\Http\Controllers\Portal;

use App\Constants\CompanyInquiryStatus;
use App\Http\Controllers\Controller;
use App\Models\Portal\Company;
use App\Models\Portal\CompanyInquiry;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Anfragen im Betriebsbereich (#33, Epic #29): Liste, Detail, Statuswechsel.
 * Die Anfragen legt StoreCompanyInquiry aus dem Leadsystem-Webhook an.
 *
 * Anfragen werden manuell aufgeloest statt per Route Model Binding, weil
 * SubstituteBindings vor der Tenancy-Initialisierung laeuft (wie in
 * OwnerDashboardController). Die Views gibt es nur im Theme sun-v2; andere
 * Themes bekommen 404 statt eines Fehlers.
 */
class OwnerInquiryController extends Controller
{
    private const PER_PAGE = 20;

    public function index(Request $request): View
    {
        $this->ensureThemeSupport('pages.dashboard.inquiries.index');
        Gate::authorize('viewAny', CompanyInquiry::class);

        $company = $this->getCompany();
        $filter = CompanyInquiryStatus::tryFrom((string) $request->query('status'));

        $inquiries = $company->inquiries()
            ->when($filter, fn ($query) => $query->withStatus($filter))
            ->latest('received_at')
            ->latest('id')
            ->paginate(self::PER_PAGE)
            ->withQueryString();

        $counts = $company->inquiries()
            ->toBase()
            ->selectRaw('status, count(*) as aggregate')
            ->groupBy('status')
            ->pluck('aggregate', 'status')
            ->map(fn ($count) => (int) $count)
            ->all();

        return view('pages.dashboard.inquiries.index', compact('company', 'inquiries', 'filter', 'counts'));
    }

    public function show(int $inquiry): View
    {
        $this->ensureThemeSupport('pages.dashboard.inquiries.show');

        $company = $this->getCompany();
        $inquiry = CompanyInquiry::findOrFail($inquiry);
        Gate::authorize('view', $inquiry);

        $inquiry->markRead();

        return view('pages.dashboard.inquiries.show', compact('company', 'inquiry'));
    }

    public function updateStatus(Request $request, int $inquiry): RedirectResponse
    {
        $inquiry = CompanyInquiry::findOrFail($inquiry);
        Gate::authorize('update', $inquiry);

        $validated = $request->validate([
            'status' => ['required', Rule::enum(CompanyInquiryStatus::class)],
        ]);

        $inquiry->forceFill([
            'status' => $validated['status'],
            'read_at' => $inquiry->read_at ?? now(),
        ])->save();

        return redirect()
            ->route('portal.owner.inquiries.show', $inquiry->id)
            ->with('success', __('portal.owner.inquiries.status_saved'));
    }

    private function getCompany(): Company
    {
        return Company::ownedBy(Auth::id())->firstOrFail();
    }

    private function ensureThemeSupport(string $view): void
    {
        abort_unless(view()->exists($view), 404);
    }
}
