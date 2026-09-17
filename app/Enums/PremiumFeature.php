<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Feature-Keys des Premium-Moduls (#2). Welche Stufe welches Feature
 * freischaltet, steht in config/premium.php unter tiers.*.features.
 */
enum PremiumFeature: string
{
    case AdFree = 'ad_free';
    case GalleryPhotos = 'gallery_photos';
    case VideoEmbed = 'video_embed';
    case References = 'references';
    case ServiceCatalog = 'service_catalog';
    case VerifiedBadge = 'verified_badge';
    case ReviewReplies = 'review_replies';
    case ReviewWidget = 'review_widget';
    case ExclusiveLeads = 'exclusive_leads';
    case LeadQuota = 'lead_quota';
    case JobPostings = 'job_postings';
    case JobHighlight = 'job_highlight';
    case Statistics = 'statistics';
    case MonthlyReport = 'monthly_report';
    case FeaturedPlacement = 'featured_placement';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    /**
     * @return array<string, string>
     */
    public static function options(): array
    {
        return collect(self::cases())
            ->mapWithKeys(fn (self $feature): array => [$feature->value => $feature->label()])
            ->all();
    }

    public function label(): string
    {
        return match ($this) {
            self::AdFree => 'Werbefreies Profil',
            self::GalleryPhotos => 'Fotogalerie',
            self::VideoEmbed => 'Video im Profil',
            self::References => 'Projektreferenzen',
            self::ServiceCatalog => 'Leistungskatalog',
            self::VerifiedBadge => 'Verifiziert-Badge',
            self::ReviewReplies => 'Antworten auf Bewertungen',
            self::ReviewWidget => 'Bewertungs-Widget für die eigene Website',
            self::ExclusiveLeads => 'Exklusive Anfragen',
            self::LeadQuota => 'Anfragen-Kontingent',
            self::JobPostings => 'Stellenanzeigen',
            self::JobHighlight => 'Hervorgehobene Stellenanzeigen',
            self::Statistics => 'Statistiken',
            self::MonthlyReport => 'Monatlicher Report per E-Mail',
            self::FeaturedPlacement => 'Top-Platzierung',
        };
    }
}
