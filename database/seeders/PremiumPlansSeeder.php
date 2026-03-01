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
        $basisProduct = Product::updateOrCreate(
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

        $this->command->info('Premium-Pläne in DB erstellt.');

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
            $this->command->warn('Pläne existieren lokal, werden beim ersten Checkout automatisch in Stripe angelegt.');
            return;
        }

        $stripeProvider = PaymentProvider::where('slug', PaymentProviderConstants::STRIPE_SLUG)->first();

        if (! $stripeProvider || ! $stripeProvider->is_active) {
            $this->command->warn('Stripe PaymentProvider nicht aktiv — Stripe-Sync übersprungen.');
            return;
        }

        try {
            $stripe = new StripeClient($stripeSecretKey);

            // Stripe-Produkt für Premium erstellen/wiederverwenden
            $stripeProductId = $this->findOrCreateStripeProduct(
                $stripe, $premiumMonthly, $stripeProvider
            );

            // Stripe-Preise erstellen/wiederverwenden
            $this->findOrCreateStripePrice(
                $stripe, $stripeProvider, $stripeProductId,
                $premiumMonthly, $monthlyPrice,
                'month', 1, 990, 'eur'
            );

            $this->findOrCreateStripePrice(
                $stripe, $stripeProvider, $stripeProductId,
                $premiumYearly, $yearlyPrice,
                'year', 1, 9900, 'eur'
            );

            $this->command->info('Stripe-Sync erfolgreich:');
            $this->command->info("  - Stripe Product: {$stripeProductId}");
            $this->command->info('  - Stripe Prices für Monatlich + Jährlich angelegt/verknüpft.');

        } catch (\Exception $e) {
            $this->command->error('Stripe-Sync fehlgeschlagen: ' . $e->getMessage());
            Log::error('PremiumPlansSeeder Stripe-Sync Fehler', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            $this->command->warn('Pläne existieren lokal. Stripe-Objekte werden beim ersten Checkout automatisch angelegt.');
        }
    }

    private function findOrCreateStripeProduct(
        StripeClient $stripe,
        Plan $plan,
        PaymentProvider $stripeProvider,
    ): string {
        // Prüfen ob bereits ein Mapping existiert
        $existing = PlanPaymentProviderData::where('plan_id', $plan->id)
            ->where('payment_provider_id', $stripeProvider->id)
            ->first();

        if ($existing) {
            $this->command->info("  Stripe Product bereits verknüpft: {$existing->payment_provider_product_id}");
            return $existing->payment_provider_product_id;
        }

        // Neues Stripe-Produkt erstellen
        $stripeProduct = $stripe->products->create([
            'name' => 'Premium Firmeneintrag',
            'description' => 'Maximale Sichtbarkeit und erweiterte Funktionen für Ihr Unternehmen',
        ]);

        // Mapping für BEIDE Pläne speichern (Monatlich + Jährlich teilen sich ein Stripe-Produkt)
        PlanPaymentProviderData::updateOrCreate(
            ['plan_id' => $plan->id, 'payment_provider_id' => $stripeProvider->id],
            ['payment_provider_product_id' => $stripeProduct->id]
        );

        return $stripeProduct->id;
    }

    private function findOrCreateStripePrice(
        StripeClient $stripe,
        PaymentProvider $stripeProvider,
        string $stripeProductId,
        Plan $plan,
        PlanPrice $planPrice,
        string $interval,
        int $intervalCount,
        int $unitAmountCent,
        string $currency,
    ): string {
        // Prüfen ob bereits ein Mapping existiert
        $existing = PlanPricePaymentProviderData::where('plan_price_id', $planPrice->id)
            ->where('payment_provider_id', $stripeProvider->id)
            ->first();

        if ($existing) {
            $this->command->info("  Stripe Price bereits verknüpft: {$existing->payment_provider_price_id} ({$interval})");
            return $existing->payment_provider_price_id;
        }

        // Plan-Product-Mapping sicherstellen (Jährlich braucht eigenes Mapping)
        PlanPaymentProviderData::updateOrCreate(
            ['plan_id' => $plan->id, 'payment_provider_id' => $stripeProvider->id],
            ['payment_provider_product_id' => $stripeProductId]
        );

        // Stripe-Preis erstellen
        $stripePrice = $stripe->prices->create([
            'product' => $stripeProductId,
            'unit_amount' => $unitAmountCent,
            'currency' => $currency,
            'recurring' => [
                'interval' => $interval,
                'interval_count' => $intervalCount,
            ],
        ]);

        // Mapping speichern
        PlanPricePaymentProviderData::updateOrCreate(
            [
                'plan_price_id' => $planPrice->id,
                'payment_provider_id' => $stripeProvider->id,
            ],
            [
                'payment_provider_price_id' => $stripePrice->id,
                'type' => PaymentProviderPlanPriceType::MAIN_PRICE->value,
            ]
        );

        $this->command->info("  Stripe Price erstellt: {$stripePrice->id} ({$interval}, {$unitAmountCent} ct)");

        return $stripePrice->id;
    }
}
