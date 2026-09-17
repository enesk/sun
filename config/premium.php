<?php

use App\Enums\PlanTier;
use App\Enums\PremiumFeature;

/*
 * Premium-Modul (#2): einzige Definition von Plaenen, Feature-Keys und Limits.
 *
 * Preise und Stripe-Preis-IDs stehen NICHT hier, sondern je Portal in der
 * Tenant-Konfiguration (App\Support\Tenancy\TenantPremiumPricing).
 * Limit null bedeutet unbegrenzt.
 */
return [

    'tiers' => [
        PlanTier::Free->value => [
            'features' => [
                PremiumFeature::GalleryPhotos->value,
                PremiumFeature::JobPostings->value,
            ],
            'limits' => [
                'gallery_photos' => 1,
                'job_postings_active' => 1,
                'lead_quota_monthly' => 0,
                'references' => 0,
            ],
        ],

        PlanTier::Pro->value => [
            'features' => [
                PremiumFeature::AdFree->value,
                PremiumFeature::GalleryPhotos->value,
                PremiumFeature::ServiceCatalog->value,
                PremiumFeature::VerifiedBadge->value,
                PremiumFeature::ReviewReplies->value,
                PremiumFeature::ExclusiveLeads->value,
                PremiumFeature::LeadQuota->value,
                PremiumFeature::JobPostings->value,
                PremiumFeature::Statistics->value,
                // Monatsreport fuer Pro und Premium (#16)
                PremiumFeature::MonthlyReport->value,
            ],
            'limits' => [
                'gallery_photos' => 20,
                'job_postings_active' => 3,
                'lead_quota_monthly' => 3,
                'references' => 0,
            ],
        ],

        PlanTier::Premium->value => [
            'features' => PremiumFeature::values(),
            'limits' => [
                'gallery_photos' => 50,
                'job_postings_active' => null,
                'lead_quota_monthly' => 10,
                'references' => 20,
            ],
        ],
    ],

    // Feature => Limit-Key unter tiers.*.limits (CompanyEntitlementService::limit, #4)
    'feature_limits' => [
        PremiumFeature::GalleryPhotos->value => 'gallery_photos',
        PremiumFeature::JobPostings->value => 'job_postings_active',
        PremiumFeature::LeadQuota->value => 'lead_quota_monthly',
        PremiumFeature::References->value => 'references',
    ],

    /*
     * Cache der Freischaltungen (#4). Store null = Standard-Store; in
     * Produktion mit Redis PREMIUM_ENTITLEMENT_CACHE_STORE=redis setzen.
     * Der Key enthaelt die Tenant-ID.
     */
    'entitlements' => [
        'cache_store' => env('PREMIUM_ENTITLEMENT_CACHE_STORE'),
        'cache_ttl' => 300,
    ],

    // Top-Platzierung (#6): Slots je Stadt x Branche
    'featured_slots_per_city_category' => 3,

    // Tage nach fehlgeschlagener Zahlung/Ablauf bis zum Auto-Downgrade (#5)
    'grace_period_days' => 7,

    /*
     * SaasyKit-Plan-Slug => PlanTier. Das Add-on featured-monthly hebt keine
     * Stufe an, sondern bucht eine Top-Platzierung (#6) und fehlt deshalb hier.
     */
    'plan_tiers' => [
        'pro-monthly' => PlanTier::Pro->value,
        'pro-yearly' => PlanTier::Pro->value,
        'premium-monthly' => PlanTier::Premium->value,
        'premium-yearly' => PlanTier::Premium->value,
    ],

    'addon_plans' => [
        'featured-monthly' => PremiumFeature::FeaturedPlacement->value,
    ],

    /*
     * Preis-Key der Tenant-Konfiguration => SaasyKit-Plan-Slug.
     * Grundlage fuer PremiumPlansSeeder und die Preisseite (#17).
     */
    'price_plans' => [
        'pro_monthly' => 'pro-monthly',
        'pro_yearly' => 'pro-yearly',
        'premium_monthly' => 'premium-monthly',
        'premium_yearly' => 'premium-yearly',
        'featured_monthly' => 'featured-monthly',
    ],

    /*
     * Bruttopreise in Cent, fuer alle Portale gleich (#38, Pro/Premium von
     * Enes am 17.09.2026 auf 49/79 EUR gesetzt, Jahr = 10 Monate). Einzige
     * Quelle der Betraege: PremiumPricingSeeder schreibt sie in die
     * Portal-Preise und in den SaasyKit-PlanPrice und legt die passenden
     * Stripe-Preise an. PremiumPlansSeeder nutzt sie nur beim
     * ersten Anlegen eines PlanPrice.
     */
    'reference_prices_cents' => [
        'pro_monthly' => 4900,
        'pro_yearly' => 49000,
        'premium_monthly' => 7900,
        'premium_yearly' => 79000,
        'featured_monthly' => 3900,
    ],

    'trial_days' => 30,

    /*
     * Statistik-Tracking (#15): Rohevents in company_events, naechtliche
     * Aggregation in company_stats_daily (stats:aggregate-daily). Erfasst wird
     * fuer alle Betriebe, angezeigt nur mit Feature statistics.
     */
    'stats' => [
        'queue' => 'default',
        'raw_retention_days' => 14,
        // Teilstrings im User-Agent (klein geschrieben); leerer User-Agent gilt ebenfalls als Bot
        'bot_user_agents' => [
            'bot', 'crawl', 'spider', 'slurp', 'mediapartners', 'adsbot',
            'facebookexternalhit', 'linkedinbot', 'twitterbot', 'embedly',
            'whatsapp', 'telegram', 'preview', 'headless', 'phantomjs',
            'lighthouse', 'pagespeed', 'gtmetrix', 'pingdom', 'uptimerobot',
            'statuscake', 'python-requests', 'python-urllib', 'curl/', 'wget/',
            'go-http-client', 'java/', 'php/', 'ruby/', 'okhttp', 'axios/',
            'node-fetch', 'scrapy', 'httpclient', 'monitor',
        ],
    ],

    /*
     * Verifiziert-Badge (#11): Nachweise liegen auf dem Disk 'private'
     * (mandantengetrennt) und werden retention_days nach der Entscheidung
     * geloescht (premium:purge-verification-documents).
     */
    'verification' => [
        'disk' => 'private',
        'directory' => 'verifications',
        'max_kb' => 10240,
        'mimes' => ['pdf', 'jpg', 'jpeg', 'png'],
        'retention_days' => 90,
        'document_link_ttl_minutes' => 30,
    ],

    /*
     * Profil-Ausbau (#13). Anzahl der Galeriefotos und Referenzen je Stufe
     * steht unter tiers.*.limits; hier nur stufenunabhaengige Grenzen.
     */
    'profile' => [
        'gallery_max_kb' => 5120,
        'reference_photos_max' => 5,
        'reference_photo_max_kb' => 5120,
        'services_max' => 50,
        // Erlaubte Video-Hosts; Ausspielung nur als Klick-zum-Laden-Embed
        'video_hosts' => ['youtube.com', 'www.youtube.com', 'm.youtube.com', 'youtu.be', 'vimeo.com', 'www.vimeo.com', 'player.vimeo.com'],
    ],

    'currency' => 'EUR',
];
