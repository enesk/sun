<?php

namespace Database\Seeders;

use App\Constants\PlanType;
use App\Enums\PlanTier;
use App\Enums\PremiumFeature;
use App\Models\Currency;
use App\Models\Interval;
use App\Models\Plan;
use App\Models\PlanPrice;
use App\Models\Product;
use App\Support\Tenancy\TenantPremiumPricing;
use Illuminate\Database\Seeder;

/**
 * SaasyKit-Products und -Plans des Premium-Moduls (#2), idempotent.
 *
 * Stufen, Features und Limits kommen aus config/premium.php. Stripe-Preise
 * werden hier bewusst nicht mehr angelegt: sie sind je Portal in der
 * Tenant-Konfiguration hinterlegt (TenantPremiumPricing). Bestehende
 * Provider-Zuordnungen bleiben unangetastet.
 */
class PremiumPlansSeeder extends Seeder
{
    private const PRODUCTS = [
        'free' => [
            'slug' => 'basis',
            'description' => 'Kostenloser Firmeneintrag mit Grundfunktionen',
            'is_default' => true,
            'is_popular' => false,
        ],
        'pro' => [
            'slug' => 'pro',
            'description' => 'Mehr Sichtbarkeit und exklusive Anfragen für Ihren Betrieb',
            'is_default' => false,
            'is_popular' => false,
        ],
        'premium' => [
            'slug' => 'premium',
            'description' => 'Maximale Sichtbarkeit und alle Funktionen für Ihr Unternehmen',
            'is_default' => false,
            'is_popular' => true,
        ],
    ];

    public function run(): void
    {
        $currency = Currency::where('code', config('premium.currency'))->firstOrFail();
        $intervals = Interval::whereIn('slug', ['day', 'month', 'year'])->get()->keyBy('slug');

        foreach (['day', 'month', 'year'] as $slug) {
            if (! $intervals->has($slug)) {
                throw new \RuntimeException("Intervall '{$slug}' fehlt — IntervalsSeeder zuerst ausführen.");
            }
        }

        $products = [];

        foreach (PlanTier::cases() as $tier) {
            $products[$tier->value] = $this->seedTierProduct($tier);
        }

        $products['featured'] = Product::updateOrCreate(
            ['slug' => 'top-platzierung'],
            [
                'name' => PremiumFeature::FeaturedPlacement->label(),
                'description' => 'Ihr Betrieb unter den ersten Einträgen der Stadt- und Branchenseiten',
                'is_default' => false,
                'is_popular' => false,
                'features' => [['feature' => PremiumFeature::FeaturedPlacement->label()]],
                'metadata' => ['addon' => PremiumFeature::FeaturedPlacement->value],
            ]
        );

        foreach ((array) config('premium.price_plans') as $priceKey => $planSlug) {
            $tier = PlanTier::forPlanSlug($planSlug);
            $isYearly = str_ends_with($priceKey, '_yearly');
            $product = $tier !== null ? $products[$tier->value] : $products['featured'];
            $hasTrial = $tier !== null && (int) config('premium.trial_days') > 0;

            $plan = Plan::updateOrCreate(
                ['slug' => $planSlug],
                [
                    'name' => TenantPremiumPricing::label($priceKey),
                    'product_id' => $product->id,
                    'interval_id' => $intervals[$isYearly ? 'year' : 'month']->id,
                    'interval_count' => 1,
                    'type' => PlanType::FLAT_RATE->value,
                    'is_active' => true,
                    'is_visible' => $tier !== null,
                    'has_trial' => $hasTrial,
                    'trial_interval_id' => $hasTrial ? $intervals['day']->id : null,
                    'trial_interval_count' => $hasTrial ? (int) config('premium.trial_days') : 0,
                    'description' => $isYearly
                        ? 'Jährliche Abrechnung, Vertragslaufzeit 12 Monate.'
                        : 'Monatliche Abrechnung, Vertragslaufzeit 12 Monate.',
                ]
            );

            // Nur beim ersten Anlegen: ein geaenderter Betrag wuerde die
            // Provider-Zuordnungen des PlanPrice loeschen (PlanPrice::booted).
            PlanPrice::firstOrCreate(
                ['plan_id' => $plan->id, 'currency_id' => $currency->id],
                [
                    'price' => (int) config("premium.reference_prices_cents.{$priceKey}"),
                    'type' => PlanType::FLAT_RATE->value,
                ]
            );
        }

        $this->command?->info('✓ Premium-Products und -Plans angelegt bzw. aktualisiert.');
    }

    private function seedTierProduct(PlanTier $tier): Product
    {
        $definition = self::PRODUCTS[$tier->value];
        $gallery = $tier->limit('gallery_photos');

        return Product::updateOrCreate(
            ['slug' => $definition['slug']],
            [
                'name' => $tier->label(),
                'description' => $definition['description'],
                'is_default' => $definition['is_default'],
                'is_popular' => $definition['is_popular'],
                'features' => $this->featureList($tier),
                'metadata' => [
                    'plan_tier' => $tier->value,
                    // Alt-Keys fuer Tenant::hasFeature(), abgeleitet aus config/premium.php
                    'max_images' => $gallery,
                    'show_statistics' => $tier->hasFeature(PremiumFeature::Statistics),
                    'priority_listing' => $tier->hasFeature(PremiumFeature::FeaturedPlacement),
                    'verified_badge' => $tier->hasFeature(PremiumFeature::VerifiedBadge),
                    'hide_ads' => $tier->hasFeature(PremiumFeature::AdFree),
                    'gallery_enabled' => $gallery === null || $gallery > 1,
                    'cover_image' => $tier->isAtLeast(PlanTier::Pro),
                ],
            ]
        );
    }

    /**
     * @return list<array{feature: string}>
     */
    private function featureList(PlanTier $tier): array
    {
        $limitTexts = [
            PremiumFeature::GalleryPhotos->value => fn (?int $limit): string => $limit === null
                ? 'Unbegrenzt Fotos'
                : ($limit === 1 ? '1 Foto' : "Bis zu {$limit} Fotos"),
            PremiumFeature::JobPostings->value => fn (?int $limit): string => $limit === null
                ? 'Unbegrenzt Stellenanzeigen'
                : "{$limit} aktive Stellenanzeige".($limit === 1 ? '' : 'n'),
            PremiumFeature::LeadQuota->value => fn (?int $limit): string => $limit === null
                ? 'Unbegrenzt Anfragen pro Monat'
                : "{$limit} Anfragen pro Monat",
        ];
        $limitKeys = [
            PremiumFeature::GalleryPhotos->value => 'gallery_photos',
            PremiumFeature::JobPostings->value => 'job_postings_active',
            PremiumFeature::LeadQuota->value => 'lead_quota_monthly',
        ];

        $list = [['feature' => 'Firmeneintrag mit Kontaktdaten']];

        foreach ($tier->features() as $feature) {
            $text = isset($limitTexts[$feature->value])
                ? $limitTexts[$feature->value]($tier->limit($limitKeys[$feature->value]))
                : $feature->label();

            $list[] = ['feature' => $text];
        }

        return $list;
    }
}
