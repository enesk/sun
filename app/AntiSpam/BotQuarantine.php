<?php

declare(strict_types=1);

namespace App\AntiSpam;

use App\AntiSpam\Support\AntiSpamConfig;
use App\AntiSpam\Support\BotScore;
use App\Models\Portal\Company;
use App\Models\User;
use App\Services\Seo\StructuredDataService;

/**
 * Markieren, Freigeben und Ablaufen der Quarantaene (#10).
 *
 * Eine Stelle fuer alle drei Wege, weil sie von drei Aufrufern kommen:
 * `antispam:scan`, der Sicht "Verdächtige Accounts/Einträge" in Filament und
 * `antispam:expire-quarantine`. Wuerde jeder selbst schreiben, waere nach dem
 * ersten Pflegeschritt nicht mehr sicher, dass "Freigeben" genau das
 * zurueckdreht, was "Markieren" angerichtet hat.
 *
 * Was eine Markierung tut:
 *
 *   Konto   `suspected_bot_*` gesetzt, `is_blocked = 1`
 *   Eintrag `suspected_bot_*` gesetzt, `is_active = 0` (Stellung "pending",
 *           damit sofort aus Suche, Stadtseiten und Sitemap heraus)
 *
 * Der Zustand davor steht in `suspected_bot_reasons_json` unter `previous`.
 * "Freigeben" stellt ihn wieder her — ein Eintrag, der vorher nie offen war,
 * wird durch eine Freigabe also nicht veroeffentlicht.
 *
 * Geschrieben wird mit `saveQuietly()`: der CompanyActivationObserver schickt
 * beim Wechsel von `is_active` auf true eine Freischaltmail, und eine Sichtung
 * im Admin ist kein Anlass fuer Post an den Inhaber. Den einen Nebeneffekt,
 * den wir brauchen — das JSON-LD des Profils verwerfen —, loest diese Klasse
 * selbst aus.
 *
 * Hart geloescht wird nie: {@see self::softDelete()} setzt `deleted_at`.
 */
class BotQuarantine
{
    /**
     * Markiert einen Datensatz. Gibt false zurueck, wenn er freigegeben ist —
     * eine Freigabe ist endgueltig, ein freigegebener Datensatz wird nie
     * wieder markiert.
     */
    public function mark(User|Company $record, BotScore $score): bool
    {
        if ($this->isCleared($record)) {
            return false;
        }

        $previous = $this->previousOf($record);

        $record->forceFill([
            // Bei einer zweiten Bewertung bleibt der erste Zeitpunkt stehen:
            // die 14-Tage-Frist darf ein Lauf nicht neu starten.
            'suspected_bot_at' => $record->suspected_bot_at ?? now(),
            'suspected_bot_score' => $score->score,
            'suspected_bot_reasons_json' => [
                'reasons' => $score->reasons,
                'previous' => $previous,
            ],
        ]);

        if ($record instanceof User) {
            $record->forceFill(['is_blocked' => true]);
        }

        if ($record instanceof Company) {
            $record->forceFill(['is_active' => false]);
        }

        $record->saveQuietly();
        $this->forgetCaches($record);

        return true;
    }

    /**
     * Freigabe: Markierung weg, vorheriger Zustand zurueck, und der Datensatz
     * ist ab jetzt dauerhaft aus der Erkennung heraus.
     */
    public function release(User|Company $record): void
    {
        $previous = (array) data_get($record->suspected_bot_reasons_json, 'previous', []);

        $record->forceFill([
            'suspected_bot_at' => null,
            'suspected_bot_score' => null,
            'suspected_bot_reasons_json' => null,
            'suspected_bot_cleared_at' => now(),
        ]);

        if ($record instanceof User && array_key_exists('is_blocked', $previous)) {
            $record->forceFill(['is_blocked' => (bool) $previous['is_blocked']]);
        }

        if ($record instanceof Company && array_key_exists('is_active', $previous)) {
            $record->forceFill(['is_active' => (bool) $previous['is_active']]);
        }

        $record->saveQuietly();
        $this->forgetCaches($record);
    }

    /**
     * Soft-Delete. Die Markierung bleibt stehen — sie ist die Begruendung und
     * wird gebraucht, falls der Datensatz wiederhergestellt werden soll.
     */
    public function softDelete(User|Company $record): void
    {
        $record->delete();
        $this->forgetCaches($record);
    }

    public function isMarked(User|Company $record): bool
    {
        return $record->suspected_bot_at !== null;
    }

    public function isCleared(User|Company $record): bool
    {
        return $record->suspected_bot_cleared_at !== null;
    }

    /** Tage in Quarantaene bis zum Soft-Delete. */
    public static function quarantineDays(): int
    {
        return max(1, AntiSpamConfig::int('suspected_bots.quarantine_days', 14));
    }

    /**
     * @return array<string, bool>
     */
    private function previousOf(User|Company $record): array
    {
        // Bei einer zweiten Bewertung den schon festgehaltenen Zustand
        // behalten — sonst waere "vorher" der Zustand der Quarantaene.
        $previous = (array) data_get($record->suspected_bot_reasons_json, 'previous', []);

        if ($previous !== []) {
            /** @var array<string, bool> $previous */
            return $previous;
        }

        if ($record instanceof User) {
            return ['is_blocked' => (bool) $record->is_blocked];
        }

        if ($record instanceof Company) {
            return ['is_active' => (bool) $record->is_active];
        }

        return [];
    }

    private function forgetCaches(User|Company $record): void
    {
        if ($record instanceof Company) {
            StructuredDataService::forget((int) $record->getKey());
        }
    }
}
