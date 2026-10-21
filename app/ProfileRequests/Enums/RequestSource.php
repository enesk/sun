<?php

declare(strict_types=1);

namespace App\ProfileRequests\Enums;

/**
 * Eingangskanal einer Profil-Anfrage (SUN-RQ, Ticket #2).
 *
 * FORM  — Self-Service-Formular auf der Profilseite (#4); liefert einen
 *         sauberen Diff, weil die Felder mit den aktuellen Werten
 *         vorbelegt sind.
 * EMAIL — Mail an das zentrale Sammelpostfach, vom IMAP-Poller eingelesen
 *         (#5); Typ, Betrieb und Diff sind nur ein KI-Vorschlag (#6).
 * CLAIM_FLOW — Uebernahmeantrag aus dem bestehenden Claim-Dialog
 *         (App\Livewire\Portal\ClaimForm), der in #6 auf profile_requests
 *         gemappt wird.
 */
enum RequestSource: string
{
    case FORM = 'form';
    case EMAIL = 'email';
    case CLAIM_FLOW = 'claim_flow';

    public function label(): string
    {
        return match ($this) {
            self::FORM => __('Formular'),
            self::EMAIL => __('E-Mail'),
            self::CLAIM_FLOW => __('Übernahme-Dialog'),
        };
    }

    /**
     * Quellen, deren Inhalt aus einem Freitext erst erschlossen werden muss
     * — nur diese laufen durch die KI-Klassifikation (#6).
     */
    public function needsClassification(): bool
    {
        return $this === self::EMAIL;
    }

    /**
     * Quellen, die eine Rohmail mitbringen (request_attachments bzw.
     * Rohmail-Ablage mit `retention_days`, #5).
     */
    public function hasRawMail(): bool
    {
        return $this === self::EMAIL;
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
