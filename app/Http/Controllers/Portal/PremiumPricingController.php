<?php

declare(strict_types=1);

namespace App\Http\Controllers\Portal;

use App\Enums\PlanTier;
use App\Enums\PremiumFeature;
use App\Http\Controllers\Controller;
use App\Models\Portal\Category;
use App\Models\Portal\City;
use App\Models\Portal\Company;
use App\Models\Tenant;
use App\Services\Premium\CompanyEntitlementService;
use App\Services\Premium\FeaturedPlacementService;
use App\Services\Seo\SeoService;
use App\Support\Tenancy\TenantPremiumPricing;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\View\View;

/**
 * Oeffentliche Preisseite /premium (#17), ein Template fuer alle Portale.
 *
 * Plaene, Features und Limits kommen aus config/premium.php, Preise aus der
 * Tenant-Konfiguration (TenantPremiumPricing). Gebucht wird im
 * Betriebsbereich (PlanCheckout), die Seite verlinkt nur dorthin.
 * Portale ohne Theme sun-v2 bekommen 404 statt einer kaputten Seite.
 */
class PremiumPricingController extends Controller
{
    private const CITY_OPTIONS = 100;

    public function __invoke(
        Request $request,
        SeoService $seo,
        FeaturedPlacementService $placements,
        CompanyEntitlementService $entitlements,
    ): View {
        // Das Template baut auf Layout und Komponenten von sun-v2 auf
        abort_unless(view()->exists('layouts.sun'), 404);

        $seo->forPricingPage($request);

        $tenant = tenant();
        $pricing = TenantPremiumPricing::for($tenant instanceof Tenant ? $tenant : null);
        $company = $request->user()?->getOwnedCompany();

        return view('portal.premium.pricing', [
            'plans' => $this->plans($pricing),
            'features' => $this->featureRows(),
            'company' => $company,
            'currentTier' => $company !== null ? $entitlements->effectiveTier($company) : null,
            'addon' => $this->addon($request, $placements, $pricing, $company),
            'trialDays' => (int) config('premium.trial_days', 0),
            'currency' => (string) config('premium.currency', 'EUR'),
        ]);
    }

    /**
     * @return list<array{tier: PlanTier, available: bool, monthly: ?int, yearly: ?int, free_months: int}>
     */
    private function plans(TenantPremiumPricing $pricing): array
    {
        return array_map(function (PlanTier $tier) use ($pricing): array {
            if ($tier === PlanTier::Free) {
                return ['tier' => $tier, 'available' => true, 'monthly' => 0, 'yearly' => 0, 'free_months' => 0];
            }

            $monthly = $pricing->isAvailable("{$tier->value}_monthly") ? $pricing->grossCents("{$tier->value}_monthly") : null;
            $yearly = $pricing->isAvailable("{$tier->value}_yearly") ? $pricing->grossCents("{$tier->value}_yearly") : null;

            return [
                'tier' => $tier,
                'available' => $pricing->isSaleEnabled() && ($monthly !== null || $yearly !== null),
                'monthly' => $monthly,
                'yearly' => $yearly,
                'free_months' => $monthly !== null && $yearly !== null
                    ? max(0, (int) round(($monthly * 12 - $yearly) / $monthly))
                    : 0,
            ];
        }, PlanTier::cases());
    }

    /**
     * Vergleichstabelle: je Feature der Wert pro Stufe — true/false oder das
     * Limit (null = unbegrenzt) laut config('premium.feature_limits').
     *
     * @return list<array{feature: PremiumFeature, cells: array<string, bool|int|null>}>
     */
    private function featureRows(): array
    {
        $limitKeys = (array) config('premium.feature_limits', []);

        return array_map(function (PremiumFeature $feature) use ($limitKeys): array {
            $cells = [];

            foreach (PlanTier::cases() as $tier) {
                $limitKey = $limitKeys[$feature->value] ?? null;

                if (! $tier->hasFeature($feature)) {
                    $cells[$tier->value] = false;
                } elseif (is_string($limitKey)) {
                    $cells[$tier->value] = $tier->limit($limitKey);
                } else {
                    $cells[$tier->value] = true;
                }
            }

            return ['feature' => $feature, 'cells' => $cells];
        }, PremiumFeature::cases());
    }

    /**
     * Add-on-Block: Stadt und Branche aus dem Betriebsprofil, sonst aus der
     * Auswahl (?stadt=, ?branche=).
     *
     * @return array<string, mixed>
     */
    private function addon(Request $request, FeaturedPlacementService $placements, TenantPremiumPricing $pricing, ?Company $company): array
    {
        $fromProfile = $company !== null && $company->city_id !== null;

        $city = $fromProfile
            ? $company->city
            : $this->findBySlug(City::query(), $request->query('stadt'));

        $categories = Category::query()->roots()->ordered()->get(['id', 'name', 'slug']);

        // Betrieb ohne Branche: wie ohne Profil auf die erste Hauptbranche zurueckfallen
        $category = ($fromProfile ? $company->categories()->orderBy('categories.id')->first() : null)
            ?? $this->findBySlug(Category::query(), $request->query('branche'))
            ?? $categories->first();

        return [
            'available' => $pricing->isFeaturedSaleEnabled(),
            'price' => $pricing->isAvailable('featured_monthly') ? $pricing->grossCents('featured_monthly') : null,
            'max' => $placements->maxSlots(),
            'from_profile' => $fromProfile,
            'city' => $city,
            'category' => $category,
            'free' => $city !== null && $category !== null
                ? $placements->availableSlots((int) $city->getKey(), (int) $category->getKey())
                : null,
            'city_options' => $fromProfile ? collect() : $this->cityOptions(),
            'category_options' => $fromProfile ? collect() : $categories,
        ];
    }

    /**
     * Staedte mit den meisten aktiven Betrieben fuer die Auswahl.
     *
     * @return Collection<int, City>
     */
    private function cityOptions(): Collection
    {
        return City::query()
            ->whereNotNull('slug')
            ->where('slug', '!=', '')
            ->withCount(['companies' => fn ($query) => $query->active()])
            ->having('companies_count', '>', 0)
            ->orderByDesc('companies_count')
            ->limit(self::CITY_OPTIONS)
            ->get(['id', 'name', 'slug'])
            ->reject(fn (City $city): bool => City::isPlaceholderName($city->name))
            ->sortBy('name')
            ->values();
    }

    /**
     * @template TModel of \Illuminate\Database\Eloquent\Model
     *
     * @param  \Illuminate\Database\Eloquent\Builder<TModel>  $query
     * @return TModel|null
     */
    private function findBySlug($query, mixed $slug)
    {
        if (! is_string($slug) || $slug === '') {
            return null;
        }

        return $query->where('slug', $slug)->first();
    }
}
