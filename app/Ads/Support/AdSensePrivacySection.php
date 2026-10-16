<?php

declare(strict_types=1);

namespace App\Ads\Support;

use App\Ads\AdProviderRegistry;
use App\Ads\AdSlotResolver;
use App\Ads\Enums\AdPageType;
use App\Ads\Enums\AdProviderType;
use App\Ads\Models\AdPlacement;
use App\Ads\Models\AdUnit;
use App\Models\Tenant;
use App\Services\TenantBrandingService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\View;

/**
 * Der Werbe-Abschnitt der Datenschutzerklaerung (SUN-AD-009, Tickets #9, #26
 * und #35).
 *
 * Der Text liegt EINMAL im Repository
 * (resources/views/legal/partials/adsense-privacy.blade.php) und wird beim
 * Rendern an den gepflegten Portaltext gehaengt — nicht in die 23 Tenant-DBs
 * kopiert. Begruendung: ein Rechtstext, der die Ausspielung beschreibt, muss
 * sich mit der Ausspielung aendern, und das kann er nur an einer Stelle.
 *
 * Angehaengt wird vor der Theme-Schicht
 * ({@see \App\Http\Controllers\Portal\StaticPageController::datenschutz()}),
 * damit sun-v2 den Abschnitt durch dieselbe Zerlegung schickt wie den
 * uebrigen Text (eigene Sprungmarke, Eintrag im Inhaltsverzeichnis) und
 * default/starter ihn ohne eigenen Einbau mitnehmen.
 *
 * Der Abschnitt hat **zwei** Teile, die unabhaengig voneinander erscheinen
 * ({@see sections()}):
 *
 * 1. **Google AdSense** — ausgespielt, wenn `ad_settings.ads_enabled` steht
 *    und eine wirksame Publisher-ID vorliegt ({@see adsenseActive()}).
 *    Wortlaut freigegeben in #26.
 * 2. **Weitere Werbemittel** (eigenes HTML ueber
 *    {@see \App\Ads\Providers\HtmlProvider}) — ausgespielt, wenn das Portal
 *    eine laufende HTML-Belegung hat, deren Werbemittel eine Einwilligung
 *    braucht ({@see htmlActive()}). Wortlaut aus #35.
 *
 * Warum Teil 2 an der Einwilligungspflicht haengt und nicht einfach an
 * "irgendeine HTML-Belegung": der freigegebene Absatz sagt zu, dass solche
 * Werbemittel **erst nach** der Einwilligung laden. Genau das entscheidet
 * seit Ticket #36 der Schalter `config_json->third_party` am Werbemittel
 * ({@see \App\Ads\Providers\HtmlProvider::requiresConsent()}): steht er
 * auf "Ja", wartet der Rahmen — und derselbe Wert laesst hier den Absatz
 * erscheinen. Text und Technik haengen damit an einer Angabe und koennen
 * nicht auseinanderlaufen.
 *
 * Ein HTML-Werbemittel ohne Einwilligungspflicht (eigenes Banner, laedt
 * nichts von Dritten) braucht keinen Abschnitt: es uebertraegt keine Daten an
 * Dritte, und der Abruf vom eigenen Server steht im uebrigen Text. Welcher
 * Fall vorliegt, sagt der Betreiber beim Anlegen — die Auslieferungsregel
 * dazu steht am Formular
 * ({@see \App\Ads\Forms\ProviderConfigForm::htmlLegalRule()},
 * docs/ad-slots.md 9.4).
 */
class AdSensePrivacySection
{
    public const VIEW = 'legal.partials.adsense-privacy';

    public function __construct(
        private readonly AdSlotResolver $resolver,
        private readonly TenantBrandingService $branding,
        private readonly AdProviderRegistry $providers,
    ) {}

    /**
     * Den gepflegten Datenschutztext um den Abschnitt ergaenzen. `null` oder
     * leer bleibt leer: ein Portal ohne Datenschutzerklaerung bekommt keine
     * Seite, die nur aus dem Werbe-Abschnitt besteht.
     */
    public function appendTo(?string $html, ?Tenant $tenant = null): ?string
    {
        $tenant ??= tenant();

        if ($html === null || trim($html) === '') {
            return $html;
        }

        $sections = $this->sections($tenant, $html);

        if (! $sections['adsense'] && ! $sections['html']) {
            return $html;
        }

        return $html."\n".$this->render($tenant, $sections);
    }

    /**
     * Spielt dieses Portal Werbung aus, die im Rechtstext und im
     * Cookie-Hinweis vorkommen muss? Die Themes fragen damit, ob die
     * Kategorie „Marketing" den Werbe-Text bekommt.
     */
    public function active(?Tenant $tenant = null): bool
    {
        $tenant ??= tenant();

        return $this->adsenseActive($tenant) || $this->htmlActive($tenant);
    }

    /** Spielt dieses Portal AdSense aus? */
    public function adsenseActive(?Tenant $tenant = null): bool
    {
        $tenant ??= tenant();

        if ($tenant === null || ! $this->resolver->adsEnabled($tenant)) {
            return false;
        }

        $publisherId = trim((string) ($this->resolver->forPageType(AdPageType::HOME, $tenant)['publisher_id'] ?? ''));

        return $publisherId !== '';
    }

    /**
     * Laeuft auf diesem Portal ein eigenes HTML-Werbemittel, das eine
     * Einwilligung braucht — also Inhalte Dritter nachlaedt?
     *
     * Gefragt wird der Provider je Werbemittel, nicht die Spalte: ob ein
     * HTML-Werbemittel einwilligungspflichtig ist, entscheidet
     * {@see \App\Ads\Providers\HtmlProvider::requiresConsent()} — seit #36
     * ueber `config_json->third_party`, Vorgabe "Einwilligung noetig".
     *
     * Eigene Abfrage statt der Karte des Resolvers: die laeuft je Seitentyp,
     * hier geht es um das ganze Portal. Deshalb auch ein eigener
     * Cache-Eintrag — die Methode haengt am Cookie-Hinweis und damit an
     * jedem Seitenaufruf.
     */
    public function htmlActive(?Tenant $tenant = null): bool
    {
        $tenant ??= tenant();

        if ($tenant === null || ! $this->resolver->adsEnabled($tenant)) {
            return false;
        }

        $tenantId = $tenant->getKey();

        return (bool) Cache::remember(
            AdCache::htmlConsentKey($tenantId),
            AdCache::ttl(),
            function () use ($tenantId): bool {
                // Gefragt wird ueber die Belegungen, nicht ueber die
                // Werbemittel: `deliverable()` (Status, Zeitraum,
                // `is_active`) und `forTenant()` (netzweit oder im Pivot)
                // stehen dort und sollen nicht zweimal formuliert werden.
                $placements = AdPlacement::query()
                    ->with('adUnit')
                    ->deliverable()
                    ->forTenant($tenantId)
                    ->whereHas('adUnit', fn ($query) => $query->where('provider', AdProviderType::HTML->value))
                    ->get();

                foreach ($placements as $placement) {
                    $unit = $placement->adUnit;

                    if (! $unit instanceof AdUnit) {
                        continue;
                    }

                    $provider = $this->providers->for($unit);

                    if ($provider !== null && $provider->requiresConsent($unit)) {
                        return true;
                    }
                }

                return false;
            },
        );
    }

    /**
     * Welche Teile dieses Portal braucht. `$existing` ist der gepflegte Text:
     * behandelt er ein Thema schon selbst, bleibt der Teil weg — sonst
     * stuenden zwei Abschnitte zum selben Thema auf der Seite.
     *
     * @return array{adsense: bool, html: bool}
     */
    public function sections(?Tenant $tenant = null, ?string $existing = null): array
    {
        $tenant ??= tenant();
        $text = $existing === null ? '' : mb_strtolower($existing);

        return [
            'adsense' => $this->adsenseActive($tenant) && ! str_contains($text, 'adsense'),
            // Bewusst **nicht** von „AdSense" im gepflegten Text verdraengt:
            // dass ein Portal AdSense selbst beschreibt, sagt nichts darueber,
            // dass es auch eigene Werbemittel von Dritten einbindet. Verdraengt
            // wird der Teil nur von der eigenen Ueberschrift — dieselbe
            // Textsuche, die auch die Nachzugskommandos idempotent haelt.
            'html' => $this->htmlActive($tenant) && ! str_contains($text, 'weitere werbemittel'),
        ];
    }

    /**
     * Der Abschnitt als HTML, Platzhalter wie im uebrigen Rechtstext
     * aufgeloest. Ohne `$sections` werden die Teile aus dem Portal bestimmt.
     *
     * @param  array{adsense: bool, html: bool}|null  $sections
     */
    public function render(?Tenant $tenant = null, ?array $sections = null): string
    {
        $tenant ??= tenant();
        $sections ??= $this->sections($tenant);

        $html = trim((string) View::make(self::VIEW, [
            'showAdSense' => $sections['adsense'],
            'showHtml' => $sections['html'],
        ])->render());

        // Ohne Tenant bleiben die Platzhalter stehen — das passiert nur
        // ausserhalb eines Portals (zentrale Domain), wo die Seite nicht laeuft.
        return $tenant instanceof Tenant
            ? $this->branding->resolveLegalPlaceholders($html, $tenant)
            : $html;
    }
}
