<?php

declare(strict_types=1);

namespace App\Ads\Dto;

use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\HtmlString;

/**
 * Das Ergebnis eines Providers (SUN-AD-003, Ticket #4): das Fragment fuer den
 * reservierten Container plus die Skripte, die es im <head> braucht.
 *
 * Bewusst **ohne** Container und ohne Hoehenreservierung — die setzt die
 * Komponente aus dem Katalog (docs/ad-slots.md 7). Und bewusst nicht
 * gecacht: der Resolver legt nur die Aufloesung (AdResolution) in den Cache,
 * gerendert wird je Anfrage, weil Einwilligung, Editor-Modus und
 * Publisher-ID nicht in den Cache gehoeren.
 *
 * `$html` ist fertiges, vom Provider verantwortetes Markup. Alles, was aus
 * der Verwaltung kommt, ist darin entweder maskiert oder liegt hinter einem
 * Sandbox-iframe (HtmlProvider, docs/ad-slots.md 5.2).
 */
final readonly class RenderedAd implements Htmlable
{
    /**
     * @param  list<AdHeadDependency>  $headDependencies
     * @param  array<string, mixed>  $meta  Diagnose fuer ads:resolve und den Editor
     * @param  bool  $requiresConsent  Braucht dieses Fragment eine Einwilligung
     *                                 in Werbe-Cookies? Dann haelt die
     *                                 Komponente es im <template> zurueck und
     *                                 resources/js/ads.js setzt es erst nach
     *                                 der Einwilligung ein (#9). Der Wert kommt
     *                                 vom Provider ({@see \App\Ads\Contracts\AdProvider::requiresConsent()}),
     *                                 nicht vom Kontext: der Sandbox-Rahmen des
     *                                 HtmlProviders laedt auch ohne Einwilligung.
     */
    public function __construct(
        public string $html,
        public array $headDependencies = [],
        public string $providerKey = '',
        public ?int $adUnitId = null,
        public ?int $placementId = null,
        public array $meta = [],
        public bool $requiresConsent = true,
    ) {}

    public function toHtml(): string
    {
        return $this->html;
    }

    public function html(): HtmlString
    {
        return new HtmlString($this->html);
    }

    public function hasHeadDependencies(): bool
    {
        return $this->headDependencies !== [];
    }

    /** @param  list<AdHeadDependency>  $dependencies */
    public function withHeadDependencies(array $dependencies): self
    {
        return new self(
            $this->html,
            $dependencies,
            $this->providerKey,
            $this->adUnitId,
            $this->placementId,
            $this->meta,
            $this->requiresConsent,
        );
    }
}
