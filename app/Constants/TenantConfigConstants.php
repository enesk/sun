<?php

namespace App\Constants;

class TenantConfigConstants
{
    // Branding
    public const LOGO_PATH = 'branding.logo_path';
    public const FAVICON_PATH = 'branding.favicon_path';
    public const PRIMARY_COLOR = 'branding.primary_color';
    public const SECONDARY_COLOR = 'branding.secondary_color';
    public const ACCENT_COLOR = 'branding.accent_color';
    public const FONT_FAMILY = 'branding.font_family';
    public const BORDER_RADIUS = 'branding.border_radius';

    // Texte & SEO
    public const SITE_TITLE = 'branding.site_title';
    public const SITE_DESCRIPTION = 'branding.site_description';
    public const META_KEYWORDS = 'branding.meta_keywords';
    public const OG_IMAGE_PATH = 'branding.og_image_path';

    // Footer & Impressum
    public const FOOTER_TEXT = 'branding.footer_text';
    public const IMPRESSUM = 'branding.impressum';
    public const DATENSCHUTZ = 'branding.datenschutz';

    // Kontakt
    public const CONTACT_EMAIL = 'settings.contact_email';
    public const CONTACT_PHONE = 'settings.contact_phone';
    public const CONTACT_ADDRESS = 'settings.contact_address';

    // Social Media
    public const SOCIAL_FACEBOOK = 'settings.social_facebook';
    public const SOCIAL_INSTAGRAM = 'settings.social_instagram';
    public const SOCIAL_TWITTER = 'settings.social_twitter';
    public const SOCIAL_LINKEDIN = 'settings.social_linkedin';

    // Features
    public const REVIEWS_ENABLED = 'features.reviews_enabled';
    public const REGISTRATION_ENABLED = 'features.registration_enabled';
    public const PREMIUM_LISTINGS_ENABLED = 'features.premium_listings_enabled';

    // URL-Konfiguration
    public const COMPANY_URL_PATTERN = 'settings.company_url_pattern';

    // Analytics
    public const GOOGLE_ANALYTICS_ID = 'settings.google_analytics_id';
    public const GOOGLE_TAG_MANAGER_ID = 'settings.google_tag_manager_id';

    // Defaults
    public const DEFAULTS = [
        self::PRIMARY_COLOR => '#3B82F6',
        self::SECONDARY_COLOR => '#1E40AF',
        self::ACCENT_COLOR => '#F59E0B',
        self::FONT_FAMILY => 'Inter',
        self::BORDER_RADIUS => '0.5rem',
        self::REVIEWS_ENABLED => true,
        self::REGISTRATION_ENABLED => true,
        self::PREMIUM_LISTINGS_ENABLED => false,
        self::FOOTER_TEXT => '© {year} {tenant_name}. Alle Rechte vorbehalten.',
        self::COMPANY_URL_PATTERN => 'id-slug',
    ];

    // Felder die Datei-Uploads sind (für Storage-Handling)
    public const FILE_FIELDS = [
        self::LOGO_PATH,
        self::FAVICON_PATH,
        self::OG_IMAGE_PATH,
    ];
}
