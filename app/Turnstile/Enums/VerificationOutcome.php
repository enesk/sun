<?php

declare(strict_types=1);

namespace App\Turnstile\Enums;

/**
 * Ergebnis einer Pruefung (#2), so wie es in turnstile_verifications landet
 * und die Kennzahlen im Admin speist (#9, #12).
 *
 * Wichtig fuer die Auswertung: Error zaehlt NICHT als Blockade. Bei
 * fail_mode=open laesst die Rule nach einem Error durch (Skipped-Wirkung,
 * Error-Eintrag), bei fail_mode=closed blockiert sie. Die Fehlerquote in #12
 * rechnet deshalb Error getrennt von Failed.
 */
enum VerificationOutcome: string
{
    /** Siteverify sagt success, Hostname und Action passen. */
    case Passed = 'passed';

    /** Siteverify sagt not success, oder Hostname/Action passen nicht. */
    case Failed = 'failed';

    /** Nicht geprueft: Modul aus, Portal aus, Aktion aus, oder Testumgebung. */
    case Skipped = 'skipped';

    /** Siteverify nicht erreichbar, Zeitueberschreitung, unlesbare Antwort. */
    case Error = 'error';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    /** Darf die Anfrage weiterlaufen? Error haengt am Fail-Mode. */
    public function allowsRequest(bool $failOpen): bool
    {
        return match ($this) {
            self::Passed, self::Skipped => true,
            self::Failed => false,
            self::Error => $failOpen,
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::Passed => __('Bestanden'),
            self::Failed => __('Abgewiesen'),
            self::Skipped => __('Übersprungen'),
            self::Error => __('Fehler'),
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Passed => 'success',
            self::Failed => 'danger',
            self::Skipped => 'gray',
            self::Error => 'warning',
        };
    }
}
