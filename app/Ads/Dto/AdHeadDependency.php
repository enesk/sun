<?php

declare(strict_types=1);

namespace App\Ads\Dto;

/**
 * Ein Skript, das ein Werbemittel im <head> der Seite braucht (SUN-AD-004,
 * Ticket #4).
 *
 * Der Provider meldet die Abhaengigkeit nur an, er gibt sie nicht aus: die
 * Komponente <x-sun.ad-slot> (#5) sammelt alle Abhaengigkeiten einer Seite,
 * wirft Doppelte anhand von `handle` weg und schreibt sie einmal in den
 * Layout-Stack `head`. Sechs Anker auf einer Seite laden den AdSense-Loader
 * also genau einmal.
 *
 * `requiresConsent` entscheidet, ob die Komponente das Skript vor der
 * Einwilligung zurueckhaelt (SUN-AD-009, Ticket #9).
 */
final readonly class AdHeadDependency
{
    /**
     * @param  array<string, string|bool>  $attributes  zusaetzliche Attribute des <script>-Tags
     */
    public function __construct(
        public string $handle,
        public string $src,
        public array $attributes = [],
        public bool $requiresConsent = true,
    ) {}

    /**
     * Attribute in der Form, in der die Komponente sie ausgibt — `src` immer
     * zuerst, damit das Ergebnis stabil ist.
     *
     * @return array<string, string|bool>
     */
    public function scriptAttributes(): array
    {
        return ['src' => $this->src] + $this->attributes;
    }
}
