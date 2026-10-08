<?php

declare(strict_types=1);

namespace App\AntiSpam\Detectors;

use App\AntiSpam\Support\BotCandidate;

/**
 * Eine Erkennungsregel der Bestandsbereinigung (#10, docs/turnstile.md §7).
 *
 * Eine Regel sagt nur, ob ihr Muster zutrifft. Das Gewicht steht in
 * config/antispam.php (`suspected_bots.detectors`), die Addition und die
 * Schwelle in {@see \App\AntiSpam\BotScorer}. So laesst sich ein Muster
 * ergaenzen oder anders gewichten, ohne `antispam:scan` anzufassen.
 *
 * Regeln werden ueber den Container aufgeloest, duerfen also Abhaengigkeiten
 * im Konstruktor fordern. Sie muessen zustandslos sein: dieselbe Instanz
 * bewertet alle Datensaetze aller Portale eines Laufs.
 */
interface Detector
{
    /**
     * Stabiler Schluessel, landet in `suspected_bot_reasons_json` und damit im
     * Datenbestand — nie umbenennen, nur ergaenzen.
     */
    public function code(): string;

    /** Begruendung in der Sprache des Admins, eine knappe Zeile. */
    public function label(): string;

    /** Gilt die Regel fuer diese Art von Datensatz? */
    public function supports(BotCandidate $candidate): bool;

    public function matches(BotCandidate $candidate): bool;
}
