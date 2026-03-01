<?php

namespace Database\Seeders;

use App\Constants\PaymentProviderConstants;
use App\Constants\PaymentProviderPlanPriceType;
use App\Constants\PlanType;
use App\Models\Currency;
use App\Models\Interval;
use App\Models\PaymentProvider;
use App\Models\Plan;
use App\Models\PlanPaymentProviderData;
use App\Models\PlanPrice;
use App\Models\PlanPricePaymentProviderData;
use App\Models\Product;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Log;
use Stripe\StripeClient;

class PremiumPlansSeeder extends Seeder
{
    public function run(): void
    {
        $eurCurrency = Currency::where('code', 'EUR')->firstOrFail();
        $dayInterval = Interval::where('slug', 'day')->firstOrFail();
        $monthInterval = Interval::where('slug', 'month')->firstOrFail();
        $yearInterval = Interval::where('slug', 'year')->firstOrFail();

        // Product 1: Basis (kostenloser Eintrag)
        Product::updateOrCreate(
            ['slug' => 'basis'],
            [
                'name' => 'Basis',
                'description' => 'Kostenloser Firmeneintrag mit Grundfunktionen',
                'is_default' => true,
                'is_popular' => false,
                'features' => [
                    ['feature' => 'Firmeneintrag mit Kontaktdaten'],
                    ['feature' => 'Kategorie-Zuordnung'],
                    ['feature' => 'Bewertungen empfangen'],
                    ['feature' => '1 Foto im Profil'],
                    ['feature' => 'Standard-Platzierung in Suchergebnissen'],
                ],
                'metadata' => [
                    'max_images' => 1,
                    'show_statistics' => false,
                    'priority_listing' => false,
                    'verified_badge' => false,
                    'hide_ads' => false,
                    'gallery_enabled' => false,
                    'cover_image' => false,
                ],
            ]
        );

        // Product 2: Premium
        $premiumProduct = Product::updateOrCreate(
            ['slug' => 'premium'],
            [
                'name' => 'Premium',
                'description' => 'Maximale Sichtbarkeit und erweiterte Funktionen für Ihr Unternehmen',
                'is_default' => false,
                'is_popular' => true,
                'features' => [
                    ['feature' => 'Alles aus Basis'],
                    ['feature' => 'Top-Platzierung in Suchergebnissen'],
                    ['feature' => 'Bis zu 10 Fotos + Bildergalerie'],
                    ['feature' => 'Cover-/Banner-Bild im Profil'],
                    ['feature' => 'Verifiziert-Badge'],
                    ['feature' => 'Erweiterte Statistiken & Trends'],
                    ['feature' => 'Keine Werbung auf dem Profil'],
                    ['feature' => 'Prominente Logo-Darstellung'],
                ],
                'metadata' => [
                    'max_images' => 10,
                    'show_statistics' => true,
                    'priority_listing' => true,
                    'verified_badge' => true,
                    'hide_ads' => true,
                    'gallery_enabled' => true,
                    'cover_image' => true,
                ],
            ]
        );

        // Plan: Premium Monatlich (9,90 €)
        $premiumMonthly = Plan::updateOrCreate(
            ['slug' => 'premium-monthly'],
            [
                'name' => 'Premium Monatlich',
                'product_id' => $premiumProduct->id,
                'interval_id' => $monthInterval->id,
                'interval_count' => 1,
                'type' => PlanType::FLAT_RATE->value,
                'is_active' => true,
                'is_visible' => true,
                'has_trial' => true,
                'trial_interval_id' => $dayInterval->id,
                'trial_interval_count' => 30,
                'description' => 'Premium-Eintrag mit monatlicher Abrechnung. Jederzeit kündbar.',
            ]
        );

        // Plan: Premium Jährlich (99,00 € — 2 Monate gratis)
        $premiumYearly = Plan::updateOrCreate(
            ['slug' => 'premium-yearly'],
            [
                'name' => 'Premium Jährlich',
                'product_id' => $premiumProduct->id,
                'interval_id' => $yearInterval->id,
                'interval_count' => 1,
                'type' => PlanType::FLAT_RATE->value,
                'is_active' => true,
                'is_visible' => true,
                'has_trial' => true,
                'trial_interval_id' => $dayInterval->id,
                'trial_interval_count' => 30,
                'description' => 'Premium-Eintrag mit jährlicher Abrechnung. Sie sparen über 20 € im Jahr.',
            ]
        );

        // Preise in EUR (gespeichert in Cent)
        $monthlyPrice = PlanPrice::updateOrCreate(
            ['plan_id' => $premiumMonthly->id, 'currency_id' => $eurCurrency->id],
            [
                'price' => 990, // 9,90 €
                'type' => 'flat_rate',
            ]
        );

        $yearlyPrice = PlanPrice::updateOrCreate(
            ['plan_id' => $premiumYearly->id, 'currency_id' => $eurCurrency->id],
            [
                'price' => 9900, // 99,00 €
                'type' => 'flat_rate',
            ]
        );

        $this->command->info('✓ Premium-Pläne in DB erstellt.');

        // ── Stripe-Synchronisation ──────────────────────────────────────
        $this->syncWithStripe($premiumMonthly, $monthlyPrice, $premiumYearly, $yearlyPrice);
    }

    private function syncWithStripe(
        Plan $premiumMonthly,
        PlanPrice $monthlyPrice,
        Plan $premiumYearly,
        PlanPrice $yearlyPrice,
    ): void {
        $stripeSecretKey = config('services.stripe.secret_key');

        if (empty($stripeSecretKey)) {
            $this->command->warn('STRIPE_SECRET_KEY nicht gesetzt — Stripe-Sync übersprungen.');
            return;
        }

        $stripeProvider = PaymentProvider::where('slug', PaymentProviderConstants::STRIPE_SLUG)->first();

        if (! $stripeProvider || ! $stripeProvider->is_active) {
            $this->command->warn('Stripe PaymentProvider nicht aktiv — Stripe-Sync übersprungen.');
            return;
        }

        try {
            $stripe = new StripeClient($stripeSecretKey);

            // Alte Mappings komplett löschen — wir bauen sie sauber neu auf
            $planIds = [$premiumMonthly->id, $premiumYearly->id];
            PlanPaymentProviderData::whereIn('plan_id', $planIds)
                ->where('payment_provider_id', $stripeProvider->id)
                ->delete();
            PlanPricePaymentProviderData::whereIn('plan_price_id', [$monthlyPrice->id, $yearlyPrice->id])
                ->where('payment_provider_id', $stripeProvider->id)
                ->delete();

            // Stripe-Produkt finden oder erstellen
            $stripeProductId = $this->ensureStripeProduct($stripe);

            // Stripe-Preise finden oder erstellen
            $monthlyStripePrice = $this->ensureStripePrice(
                $stripe, $stripeProductId, 990, 'eur', 'month', 1
            );
            $yearlyStripePrice = $this->ensureStripePrice(
                $stripe, $stripeProductId, 9900, 'eur', 'year', 1
            );

            // DB-Mappings sauber anlegen
            foreach ($planIds as $planId) {
                PlanPaymentProviderData::create([
                    'plan_id' => $planId,
                    'payment_provider_id' => $stripeProvider->id,
                    'payment_provider_product_id' => $stripeProductId,
                ]);
            }

            PlanPricePaymentProviderData::create([
                'plan_price_id' => $monthlyPrice->id,
                'payment_provider_id' => $stripeProvider->id,
                'payment_provider_price_id' => $monthlyStripePrice,
                'type' => PaymentProviderPlanPriceType::MAIN_PRICE->value,
            ]);

            PlanPricePaymentProviderData::create([
                'plan_price_id' => $yearlyPrice->id,
                'payment_provider_id' => $stripeProvider->id,
                'payment_provider_price_id' => $yearlyStripePrice,
                'type' => PaymentProviderPlanPriceType::MAIN_PRICE->value,
            ]);

            $this->command->info('✓ Stripe-Sync erfolgreich:');
            $this->command->info("  Product: {$stripeProductId}");
            $this->command->info("  Price Monatlich: {$monthlyStripePrice} (990 ct/month)");
            $this->command->info("  Price Jährlich: {$yearlyStripePrice} (9900 ct/year)");

        } catch (\Exception $e) {
            $this->command->error('Stripe-Sync fehlgeschlagen: ' . $e->getMessage());
            Log::error('PremiumPlansSeeder Stripe-Sync', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
        }
    }

    /**
     * Sucht ein aktives Stripe-Produkt "Premium Firmeneintrag" oder erstellt es.
     */
    private function ensureStripeProduct(StripeClient $stripe): string
    {
        // Zuerst in Stripe nach existierendem Produkt suchen
        $products = $stripe->products->search([
            'query' => "active:'true' AND name:'Premium Firmeneintrag'",
        ]);

        if (count($products->data) > 0) {
            $productId = $products->data[0]->id;
            $this->command->info("  Stripe Product gefunden: {$productId}");
            return $productId;
        }

        // Neues Produkt erstellen
        $product = $stripe->products->create([
            'name' => 'Premium Firmeneintrag',
            'description' => 'Maximale Sichtbarkeit und erweiterte Funktionen für Ihr Unternehmen',
        ]);

        $this->command->info("  Stripe Product erstellt: {$product->id}");

        return $product->id;
    }

    /**
     * Sucht einen aktiven Stripe-Preis mit exakten Parametern oder erstellt ihn.
     */
    private function ensureStripePrice(
        StripeClient $stripe,
        string $productId,
        int $unitAmount,
        string $currency,
        string $interval,
        int $intervalCount,
    ): string {
        // Existierende aktive Preise für das Produkt laden
        $prices = $stripe->prices->all([
            'product' => $productId,
            'active' => true,
            'currency' => $currency,
            'type' => 'recurring',
            'limit' => 20,
        ]);

        foreach ($prices->data as $price) {
            if ($price->unit_amount === $unitAmount
                && $price->recurring->interval === $interval
                && $price->recurring->interval_count === $intervalCount
            ) {
                $this->command->info("  Stripe Price gefunden: {$price->id} ({$interval})");
                return $price->id;
            }
        }

        // Neuen Preis erstellen
        $price = $stripe->prices->create([
            'product' => $productId,
            'unit_amount' => $unitAmount,
            'currency' => $currency,
            'recurring' => [
                'interval' => $interval,
                'interval_count' => $intervalCount,
            ],
        ]);

        $this->command->info("  Stripe Price erstellt: {$price->id} ({$interval}, {$unitAmount} ct)");

        return $price->id;
    }
}
