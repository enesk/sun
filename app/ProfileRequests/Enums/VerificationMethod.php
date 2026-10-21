<?php

declare(strict_types=1);

namespace App\ProfileRequests\Enums;

/**
 * Wie eine Profil-Anfrage als „vom Betrieb autorisiert" gilt
 * (SUN-RQ, Ticket #2; Regeln in docs/profile-requests.md, Abschnitt 4).
 *
 * MAGIC_LINK    — Einmal-Link an die beim Profil hinterlegte Firmen-E-Mail.
 *                 Der Regelweg; gueltig `verification_ttl_hours`.
 * SENDER_DOMAIN — Die Absenderdomain der Mail entspricht der Domain der
 *                 hinterlegten Firmen-E-Mail oder der Profil-Website. Gilt
 *                 nur fuer Firmendomains, nie fuer Freemail-Anbieter
 *                 (config profile-requests.verification.freemail_domains).
 * MANUAL        — Enes bestaetigt von Hand (Nachweis per Anhang, Telefonat,
 *                 Profil ohne hinterlegte E-Mail). Nie automatisch.
 */
enum VerificationMethod: string
{
    case MAGIC_LINK = 'magic_link';
    case SENDER_DOMAIN = 'sender_domain';
    case MANUAL = 'manual';

    public function label(): string
    {
        return match ($this) {
            self::MAGIC_LINK => __('Bestätigungslink'),
            self::SENDER_DOMAIN => __('Absender-Domain'),
            self::MANUAL => __('Manuell geprüft'),
        };
    }

    /**
     * Darf eine so verifizierte Anfrage in die Auto-Regeln (#9) laufen?
     * Eine Handpruefung ist immer schon eine Entscheidung von Enes — sie
     * wird direkt angewendet, nicht noch einmal automatisiert.
     */
    public function allowsAutoApply(): bool
    {
        return $this !== self::MANUAL;
    }

    /**
     * Braucht dieser Weg eine ausgehende E-Mail an den Betrieb?
     */
    public function sendsMail(): bool
    {
        return $this === self::MAGIC_LINK;
    }

    /**
     * @return array<string, string>
     */
    public static function options(): array
    {
        return collect(self::cases())
            ->mapWithKeys(fn (self $case) => [$case->value => $case->label()])
            ->all();
    }
}
