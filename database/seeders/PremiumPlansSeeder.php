<?php

namespace Database\Seeders;

use App\Constants\PlanType;
use App\Models\Currency;
use App\Models\Interval;
use App\Models\Plan;
use App\Models\PlanPrice;
use App\Models\Product;
use Illuminate\Database\Seeder;

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
                'trial_interval_count' => 30, // 30 Tage Trial ohne Kreditkarte
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
                'trial_interval_count' => 30, // 30 Tage Trial ohne Kreditkarte
                'description' => 'Premium-Eintrag mit jährlicher Abrechnung. Sie sparen über 20 € im Jahr.',
            ]
        );

        // Preise in EUR (gespeichert in Cent)
        PlanPrice::updateOrCreate(
            ['plan_id' => $premiumMonthly->id, 'currency_id' => $eurCurrency->id],
            [
                'price' => 990, // 9,90 €
                'type' => 'flat_rate',
            ]
        );

        PlanPrice::updateOrCreate(
            ['plan_id' => $premiumYearly->id, 'currency_id' => $eurCurrency->id],
            [
                'price' => 9900, // 99,00 €
                'type' => 'flat_rate',
            ]
        );

        $this->command->info('Premium-Pläne erfolgreich erstellt:');
        $this->command->info("  - Basis (Produkt, kostenlos)");
        $this->command->info("  - Premium Monatlich: 9,90 €/Monat (30 Tage Trial ohne Kreditkarte)");
        $this->command->info("  - Premium Jährlich: 99,00 €/Jahr (30 Tage Trial ohne Kreditkarte)");
    }
}
