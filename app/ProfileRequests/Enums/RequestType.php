<?php

declare(strict_types=1);

namespace App\ProfileRequests\Enums;

/**
 * Typ einer Profil-Anfrage (SUN-RQ, Ticket #2).
 *
 * Die vier Werte sind abschliessend: die Stichprobe der 50 vorhandenen
 * Anfragen (docs/profile-requests.md, Abschnitt 2) kennt genau
 * Aenderungswuensche, Loeschwuensche und Uebernahmeantraege; alles andere
 * (Rueckfragen, Werbung, Bewertungsstreit) faellt auf OTHER und bleibt
 * Handarbeit.
 *
 * CLAIM deckt den bestehenden, bisher eigenstaendigen Uebernahme-Flow ab
 * (Tenant-Tabelle `claim_requests`, App\Services\ClaimService). Die
 * Zusammenfuehrung passiert in Ticket #6, nicht hier.
 */
enum RequestType: string
{
    case CHANGE = 'change';
    case DELETE = 'delete';
    case CLAIM = 'claim';
    case OTHER = 'other';

    public function label(): string
    {
        return match ($this) {
            self::CHANGE => __('Änderung'),
            self::DELETE => __('Löschung'),
            self::CLAIM => __('Übernahme'),
            self::OTHER => __('Sonstiges'),
        };
    }

    /**
     * Traegt dieser Typ einen strukturierten Diff (request_changes, #3)?
     */
    public function hasDiff(): bool
    {
        return $this === self::CHANGE;
    }

    /**
     * Darf dieser Typ nach erfolgreicher Verifikation ohne Enes erledigt
     * werden? CHANGE nur fuer Whitelist-Felder (config
     * profile-requests.auto_apply_fields), DELETE nur nach Karenzfrist,
     * CLAIM sofort. OTHER niemals.
     */
    public function supportsAutoApply(): bool
    {
        return $this !== self::OTHER;
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
