<?php

namespace Database\Seeders;

use App\Constants\PaymentProviderConstants;
use App\Constants\PaymentProviderPlanPriceType;
use App\Constants\PlanType;
use App\Models\Currency;
use App\Models\PaymentProvider;
use App\Models\Plan;
use App\Models\PlanPaymentProviderData;
use App\Models\PlanPrice;
use App\Models\PlanPricePaymentProviderData;
use App\Models\Tenant;
use App\Services\Premium\StripePremiumPriceProvisioner;
use App\Support\Tenancy\TenantPremiumPricing;
use App\Themes\ThemeManager;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;
use Stripe\StripeClient;

/**
 * Premium-Preise je Portal (#38), idempotent.
 *
 * Betraege kommen ausschliesslich aus config('premium.reference_prices_cents').
 * Mit Stripe-Schluessel legt der Seeder je Preis-Key einen Stripe-Price an
 * (StripePremiumPriceProvisioner, lookup_key) und laesst BEIDE Stellen darauf
 * zeigen: die Portal-Preise (TenantPremiumPricing) und den SaasyKit-PlanPrice,
 * ueber den der Checkout tatsaechlich abrechnet. Ohne Schluessel schreibt er
 * nur die Betraege, der Verkauf bleibt dann aus.
 *
 * Ein Live-Schluessel bricht ab, ausser $allowLive ist gesetzt. Aufruf mit
 * Optionen ueber `php artisan premium:pricing:seed`.
 */
class PremiumPricingSeeder extends Seeder
{
    /** @var list<string> ID, UUID oder Domain; leer = alle Portale mit sun-v2 */
    public array $tenantFilter = [];

    public bool $dryRun = false;

    public bool $allowLive = false;

    public function run(): void
    {
        $secret = trim((string) config('services.stripe.secret_key'));

        if (str_starts_with($secret, 'sk_live_') && ! $this->allowLive) {
            throw new \RuntimeException('Stripe-Live-Schlüssel erkannt – Abbruch. Live-Preise nur mit ausdrücklicher Freigabe (--live).');
        }

        $provisioner = $secret === '' ? null : $this->provisioner($secret);

        if ($provisioner === null) {
            $this->command?->warn('Kein STRIPE_SECRET_KEY gesetzt: es werden nur Beträge geschrieben, die Pakete bleiben „Derzeit nicht buchbar“.');
        }

        if ($this->dryRun) {
            $this->command?->comment('Trockenlauf – es wird nichts geschrieben und in Stripe nichts angelegt.');
        }

        $tenants = $this->tenants();

        if ($tenants->isEmpty()) {
            throw new \RuntimeException('Keine passenden Portale gefunden.');
        }

        $prices = $this->preparePrices($provisioner);

        $this->command?->table(
            ['Preis-Key', 'Betrag', 'Stripe-Price', 'Stripe'],
            array_map(fn (string $key, array $price): array => [
                $key,
                number_format($price['cents'] / 100, 2, ',', '.').' €',
                $price['price_id'] ?? '—',
                $price['status'],
            ], array_keys($prices), $prices),
        );

        foreach ($tenants as $tenant) {
            $this->applyToTenant($tenant, $prices);
        }

        $this->command?->info(($this->dryRun ? 'Würde gesetzt für: ' : 'Premium-Preise gesetzt für: ')
            .$tenants->map(fn (Tenant $t): string => "{$t->domain} (ID {$t->id})")->join(', '));
    }

    /**
     * @return array<string, array{cents: int, price_id: string|null, status: string}>
     */
    private function preparePrices(?StripePremiumPriceProvisioner $provisioner): array
    {
        $currency = (string) config('premium.currency');
        $prices = [];

        foreach ((array) config('premium.price_plans') as $key => $planSlug) {
            $cents = (int) config("premium.reference_prices_cents.{$key}");

            if ($cents <= 0) {
                throw new \RuntimeException("Kein Betrag für {$key} in config/premium.php (reference_prices_cents).");
            }

            $plan = Plan::where('slug', $planSlug)->first();

            if ($plan === null) {
                throw new \RuntimeException("Plan '{$planSlug}' fehlt – zuerst PremiumPlansSeeder ausführen.");
            }

            $interval = str_ends_with($key, '_yearly') ? 'year' : 'month';
            $priceId = null;
            $productId = null;
            $status = 'kein Schlüssel';

            if ($provisioner !== null && $this->dryRun) {
                $priceId = $provisioner->find($key, $cents);
                $status = $priceId === null ? 'würde angelegt' : 'vorhanden';
            } elseif ($provisioner !== null) {
                $result = $provisioner->ensure($key, $planSlug, TenantPremiumPricing::label($key), $cents, $currency, $interval);
                $priceId = $result['price_id'];
                $productId = $result['product_id'];
                $status = ($result['created'] ? 'angelegt' : 'vorhanden')
                    .($result['deactivated'] === [] ? '' : ', alt deaktiviert: '.implode(', ', $result['deactivated']));
            }

            if (! $this->dryRun) {
                $this->syncPlanPrice($plan, $cents, $currency, $productId, $priceId);
            }

            $prices[$key] = ['cents' => $cents, 'price_id' => $priceId, 'status' => $status];
        }

        return $prices;
    }

    /**
     * SaasyKit rechnet ueber PlanPrice und dessen Provider-Zuordnung ab.
     * Ein geaenderter Betrag loescht die Zuordnungen (PlanPrice::booted),
     * deshalb wird die Zuordnung danach immer neu auf den Stripe-Price gesetzt.
     */
    private function syncPlanPrice(Plan $plan, int $cents, string $currencyCode, ?string $productId, ?string $priceId): void
    {
        $currency = Currency::where('code', $currencyCode)->firstOrFail();

        $planPrice = PlanPrice::firstOrNew(['plan_id' => $plan->id, 'currency_id' => $currency->id]);
        $planPrice->price = $cents;
        $planPrice->type ??= PlanType::FLAT_RATE->value;
        $planPrice->save();

        if ($priceId === null || $productId === null) {
            return;
        }

        $provider = PaymentProvider::where('slug', PaymentProviderConstants::STRIPE_SLUG)->firstOrFail();

        PlanPaymentProviderData::updateOrCreate(
            ['plan_id' => $plan->id, 'payment_provider_id' => $provider->id],
            ['payment_provider_product_id' => $productId],
        );

        PlanPricePaymentProviderData::where('plan_price_id', $planPrice->id)
            ->where('payment_provider_id', $provider->id)
            ->where('payment_provider_price_id', '!=', $priceId)
            ->delete();

        PlanPricePaymentProviderData::firstOrCreate([
            'plan_price_id' => $planPrice->id,
            'payment_provider_id' => $provider->id,
            'payment_provider_price_id' => $priceId,
            'type' => PaymentProviderPlanPriceType::MAIN_PRICE->value,
        ]);
    }

    /**
     * @param  array<string, array{cents: int, price_id: string|null, status: string}>  $prices
     */
    private function applyToTenant(Tenant $tenant, array $prices): void
    {
        $values = $tenant->getAttribute(TenantPremiumPricing::ATTRIBUTE);

        if (is_string($values)) {
            $values = json_decode($values, true);
        }

        $values = is_array($values) ? $values : [];

        foreach ($prices as $key => $price) {
            $grossKey = $key.TenantPremiumPricing::GROSS_SUFFIX;
            $stripeKey = $key.TenantPremiumPricing::STRIPE_SUFFIX;

            if ($price['price_id'] !== null) {
                $values[$stripeKey] = $price['price_id'];
            } elseif ((int) ($values[$grossKey] ?? 0) !== $price['cents']) {
                // Ohne Schluessel und mit neuem Betrag gehoert eine vorhandene
                // Preis-ID zum alten Betrag: entfernen, der Verkauf bleibt aus.
                unset($values[$stripeKey]);
            }

            $values[$grossKey] = $price['cents'];
        }

        if ($this->dryRun) {
            return;
        }

        $tenant->setAttribute(TenantPremiumPricing::ATTRIBUTE, $values);
        $tenant->save();
    }

    private function provisioner(string $secret): StripePremiumPriceProvisioner
    {
        if (app()->bound(StripePremiumPriceProvisioner::class)) {
            return app(StripePremiumPriceProvisioner::class);
        }

        return new StripePremiumPriceProvisioner(new StripeClient($secret));
    }

    /**
     * @return Collection<int, Tenant>
     */
    private function tenants(): Collection
    {
        $filter = array_values(array_filter(array_map('trim', $this->tenantFilter), fn (string $v): bool => $v !== ''));

        /** @var Collection<int, Tenant> $tenants */
        $tenants = Tenant::query()->orderBy('id')->get();

        if ($filter === []) {
            return $tenants->filter(fn (Tenant $t): bool => $t->getAttribute(ThemeManager::TENANT_THEME_KEY) === 'sun-v2')->values();
        }

        return $tenants->filter(fn (Tenant $t): bool => in_array((string) $t->id, $filter, true)
            || in_array((string) $t->uuid, $filter, true)
            || in_array((string) $t->domain, $filter, true))->values();
    }
}
