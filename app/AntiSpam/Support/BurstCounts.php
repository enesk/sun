<?php

declare(strict_types=1);

namespace App\AntiSpam\Support;

/**
 * Wie viele Datensaetze im Zeitfenster dieselbe Herkunft teilen (#10, R9).
 *
 * Wird je Portal einmal aus den geladenen Zeilen berechnet
 * ({@see \App\AntiSpam\SuspectedBotScanner}) und dem Kandidaten mitgegeben —
 * eine Regel soll keine eigenen Abfragen fahren, sonst wird aus einem Lauf
 * ueber 20 Portale eine Abfrage je Datensatz und Regel.
 *
 * Gezaehlt wird immer der Kandidat selbst mit: ein Wert von 1 heisst
 * "allein".
 */
final class BurstCounts
{
    public function __construct(
        /** Datensaetze mit demselben IP-Hash im Fenster. */
        public readonly int $sameIp = 1,
        /** Datensaetze derselben Minute, unabhaengig von der Herkunft. */
        public readonly int $sameMinute = 1,
        /** Datensaetze mit derselben Mail-Domain im Fenster. */
        public readonly int $sameDomain = 1,
    ) {}
}
