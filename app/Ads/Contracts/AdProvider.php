<?php

declare(strict_types=1);

namespace App\Ads\Contracts;

use App\Ads\Dto\AdRenderContext;
use App\Ads\Dto\RenderedAd;
use App\Ads\Models\AdUnit;

/**
 * Ein Provider ist die einzige Stelle, die weiss, WIE eine Art Werbemittel
 * aussieht (docs/ad-slots.md 5, Ticket #4).
 *
 * Ein neuer Provider ist genau zwei Dinge:
 *
 *   1. eine Klasse, die dieses Interface implementiert (am einfachsten ueber
 *      {@see \App\Ads\Providers\AbstractAdProvider}),
 *   2. ein Eintrag in `config('ad-slots.providers')`.
 *
 * Weder Models noch Resolver, Komponente oder Editor fassen ihn an: das
 * Formular baut sich aus {@see configSchema()}, die Pruefung beim Speichern
 * laeuft ueber {@see validateConfig()}, die Ausgabe ueber {@see render()}.
 * Beispiel in docs/ad-slots.md 5.3 (`AffiliateProvider`).
 *
 * Provider sind zustandslos und werden als Singleton gehalten — sie duerfen
 * nichts aus Anfrage oder Tenant festhalten, alles Veraenderliche steht im
 * {@see AdRenderContext}.
 */
interface AdProvider
{
    /**
     * Schluessel des Providers. Steht so in `ad_units.provider` und als
     * Schluessel in `config('ad-slots.providers')` — beides muss gleich sein.
     */
    public function key(): string;

    /** Bezeichnung fuer Formulare und Tabellen (#8/#10). */
    public function label(): string;

    /**
     * Felder der providerspezifischen Konfiguration (`ad_units.config_json`).
     * Eine Beschreibung, kein Filament-Code — die Resource (#10) baut die
     * Komponenten daraus, und {@see validateConfig()} leitet die Regeln ab,
     * damit Formular und Model nie auseinanderlaufen.
     *
     * Je Feld: name, label, type (text|textarea|number|select), required,
     * optional help, options, rules, placeholder.
     *
     * @return list<array<string, mixed>>
     */
    public function configSchema(): array;

    /**
     * Pruefung der Konfiguration.
     *
     * @param  array<string, mixed>  $config
     * @return list<string> Fehlermeldungen; leeres Array = gueltig
     */
    public function validateConfig(array $config): array;

    /**
     * Auf die bekannten Schluessel eingedampfte Konfiguration — so landet kein
     * Beiwerk aus einem Formular in `config_json`.
     *
     * @param  array<string, mixed>  $config
     * @return array<string, mixed>
     */
    public function normalizeConfig(array $config): array;

    /**
     * Braucht die Ausspielung eine Einwilligung? Entscheidet, ob
     * <x-sun.ad-slot> den Platzhalter oder das Werbemittel rendert
     * (SUN-AD-009).
     */
    public function requiresConsent(AdUnit $unit): bool;

    /**
     * Kann dieses Werbemittel hier ausgespielt werden? Prueft die harten
     * Voraussetzungen des Providers — bei AdSense die Publisher-ID des
     * Portals, bei HTML ein nicht leeres Markup mit Groesse. Liefert false,
     * wenn der Anker leer bleiben muss.
     */
    public function supports(AdUnit $unit, AdRenderContext $context): bool;

    /**
     * Rendert den Inhalt des reservierten Containers — ohne den Container
     * selbst und ohne Hoehenreservierung, die setzt die Komponente aus dem
     * Katalog. Liefert null, wenn nichts auszuspielen ist.
     */
    public function render(AdUnit $unit, AdRenderContext $context): ?RenderedAd;
}
