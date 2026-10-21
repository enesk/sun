<?php

declare(strict_types=1);

namespace App\ProfileRequests\Enums;

/**
 * Zustand einer Profil-Anfrage (SUN-RQ, Ticket #2).
 *
 * Der Zustandsautomat ist in docs/profile-requests.md, Abschnitt 8
 * beschrieben und hier verbindlich abgebildet:
 *
 *   NEW -> CLASSIFIED -> AWAITING_VERIFICATION -> VERIFIED -> APPLIED
 *                                                        \-> REJECTED
 *   jeder offene Zustand -> REJECTED | CANCELLED
 *   AWAITING_VERIFICATION -> EXPIRED (Magic-Link abgelaufen)
 *   NEW | CLASSIFIED -> EXPIRED (ohne Fortschritt liegengeblieben, #6)
 *
 * NEW gilt nur fuer E-Mail-Anfragen, bevor die Klassifikation gelaufen ist.
 * Formular- und Claim-Anfragen starten direkt in CLASSIFIED, weil Typ,
 * Betrieb und Diff dort schon strukturiert vorliegen.
 */
enum RequestStatus: string
{
    case NEW = 'new';
    case CLASSIFIED = 'classified';
    case AWAITING_VERIFICATION = 'awaiting_verification';
    case VERIFIED = 'verified';
    case APPLIED = 'applied';
    case REJECTED = 'rejected';
    case EXPIRED = 'expired';
    case CANCELLED = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::NEW => __('Neu'),
            self::CLASSIFIED => __('Eingeordnet'),
            self::AWAITING_VERIFICATION => __('Warte auf Bestätigung'),
            self::VERIFIED => __('Bestätigt'),
            self::APPLIED => __('Erledigt'),
            self::REJECTED => __('Abgelehnt'),
            self::EXPIRED => __('Abgelaufen'),
            self::CANCELLED => __('Storniert'),
        };
    }

    /**
     * Endzustaende — ab hier aendert sich nichts mehr.
     */
    public function isFinal(): bool
    {
        return in_array($this, [self::APPLIED, self::REJECTED, self::EXPIRED, self::CANCELLED], true);
    }

    /**
     * Gehoert die Anfrage in die offene Inbox des Central-Panels (#7)?
     */
    public function isOpen(): bool
    {
        return ! $this->isFinal();
    }

    /**
     * Erlaubte Folgezustaende. Jeder Uebergang wird in request_events
     * protokolliert (#3).
     *
     * @return list<self>
     */
    public function allowedTransitions(): array
    {
        return match ($this) {
            // EXPIRED auch aus NEW und CLASSIFIED (#6): beide Zustaende sind
            // Durchgangsstationen — bleibt ein Vorgang dort laenger als
            // `verification_ttl_hours` liegen, ist die Klassifikation oder
            // die Verifikation nie gelaufen. `requests:expire` schliesst ihn
            // dann, statt ihn fuer immer in der offenen Inbox zu lassen.
            self::NEW => [self::CLASSIFIED, self::REJECTED, self::CANCELLED, self::EXPIRED],
            self::CLASSIFIED => [self::AWAITING_VERIFICATION, self::VERIFIED, self::REJECTED, self::CANCELLED, self::EXPIRED],
            self::AWAITING_VERIFICATION => [self::VERIFIED, self::EXPIRED, self::REJECTED, self::CANCELLED],
            // Enes darf eine bestaetigte Anfrage weiterhin ablehnen (z.B.
            // Loeschwunsch eines Dritten, der die Domain zwar trifft, aber
            // nicht der Betrieb ist).
            self::VERIFIED => [self::APPLIED, self::REJECTED, self::CANCELLED],
            self::APPLIED, self::REJECTED, self::EXPIRED, self::CANCELLED => [],
        };
    }

    public function canTransitionTo(self $target): bool
    {
        return in_array($target, $this->allowedTransitions(), true);
    }

    /**
     * CLASSIFIED darf die Verifikation ueberspringen, wenn die Absender-
     * Domain schon passt (VerificationMethod::SENDER_DOMAIN) — dann geht
     * es direkt nach VERIFIED.
     */
    public function needsVerification(): bool
    {
        return in_array($this, [self::NEW, self::CLASSIFIED, self::AWAITING_VERIFICATION], true);
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

    /**
     * @return list<string>
     */
    public static function openValues(): array
    {
        return collect(self::cases())
            ->filter(fn (self $case) => $case->isOpen())
            ->map(fn (self $case) => $case->value)
            ->values()
            ->all();
    }
}
