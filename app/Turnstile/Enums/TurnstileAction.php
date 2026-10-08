<?php

declare(strict_types=1);

namespace App\Turnstile\Enums;

/**
 * Geschuetzte Formulare (#2). Der Wert wandert als `action` in das Widget und
 * kommt von Cloudflare in der Siteverify-Antwort zurueck; die Rule vergleicht
 * beides (config('turnstile.verify_action')). Damit laesst sich ein Token aus
 * dem Registrierungsformular nicht im Eintragsformular einreichen.
 *
 * Werte sind Teil des Datenbestands (turnstile_verifications.action) und der
 * Tenant-Konfiguration — nie umbenennen, nur ergaenzen. Cloudflare erlaubt in
 * `action` ausschliesslich a-z, A-Z, 0-9, _ und - (max. 32 Zeichen).
 */
enum TurnstileAction: string
{
    /** Kontoanlage ueber /register und den Kontoschritt des Eintragsformulars. */
    case Registration = 'registration';

    /** "Trag deinen Betrieb ein" (App\Livewire\Portal\CompanySignup). */
    case CompanyListing = 'company_listing';

    /** Anfrage-Dialog des Leadsystems (Marktplatz und exklusiv). */
    case LeadRequest = 'lead_request';

    /** Allgemeine Kontaktwege: Kontaktformular, Korrekturvorschlag, Newsletter. */
    case Contact = 'contact';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    public static function tryFromValue(?string $value): ?self
    {
        return self::tryFrom((string) $value);
    }

    public function label(): string
    {
        return match ($this) {
            self::Registration => __('Registrierung'),
            self::CompanyListing => __('Firmeneintragung'),
            self::LeadRequest => __('Anfrage'),
            self::Contact => __('Kontakt'),
        };
    }

    /**
     * Vorgabe-Darstellung dieser Aktion, falls config/turnstile.php keinen
     * Eintrag unter `actions` hat und der Tenant nichts abweichend setzt.
     */
    public function defaultMode(): TurnstileMode
    {
        return match ($this) {
            self::Registration, self::CompanyListing => TurnstileMode::Managed,
            self::LeadRequest, self::Contact => TurnstileMode::NonInteractive,
        };
    }
}
