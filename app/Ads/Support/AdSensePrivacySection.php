<?php

declare(strict_types=1);

namespace App\Ads\Support;

use App\Ads\AdSlotResolver;
use App\Ads\Enums\AdPageType;
use App\Models\Tenant;
use App\Services\TenantBrandingService;
use Illuminate\Support\Facades\View;

/**
 * Der Abschnitt "Google AdSense" der Datenschutzerklaerung (SUN-AD-009,
 * Ticket #9).
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
 * Nicht angehaengt wird,
 * * wenn das Portal keine Werbung ausspielt (`ad_settings.ads_enabled` aus
 *   oder keine wirksame Publisher-ID) — ein Portal ohne Werbung braucht
 *   keinen Abschnitt ueber Werbung,
 * * oder wenn der gepflegte Text AdSense schon selbst behandelt; sonst
 *   stuenden zwei Abschnitte zum selben Thema auf der Seite.
 */
class AdSensePrivacySection
{
    public const VIEW = 'legal.partials.adsense-privacy';

    public function __construct(
        private readonly AdSlotResolver $resolver,
        private readonly TenantBrandingService $branding,
    ) {}

    /**
     * Den gepflegten Datenschutztext um den Abschnitt ergaenzen. `null` oder
     * leer bleibt leer: ein Portal ohne Datenschutzerklaerung bekommt keine
     * Seite, die nur aus dem Werbe-Abschnitt besteht.
     */
    public function appendTo(?string $html, ?Tenant $tenant = null): ?string
    {
        $tenant ??= tenant();

        if ($html === null || trim($html) === '' || ! $this->active($tenant)) {
            return $html;
        }

        if ($this->alreadyCovered($html)) {
            return $html;
        }

        return $html."\n".$this->render($tenant);
    }

    /** Spielt dieses Portal AdSense aus? */
    public function active(?Tenant $tenant = null): bool
    {
        $tenant ??= tenant();

        if ($tenant === null || ! $this->resolver->adsEnabled($tenant)) {
            return false;
        }

        $publisherId = trim((string) ($this->resolver->forPageType(AdPageType::HOME, $tenant)['publisher_id'] ?? ''));

        return $publisherId !== '';
    }

    /** Der Abschnitt als HTML, Platzhalter wie im uebrigen Rechtstext aufgeloest. */
    public function render(?Tenant $tenant = null): string
    {
        $tenant ??= tenant();
        $html = trim((string) View::make(self::VIEW)->render());

        // Ohne Tenant bleiben die Platzhalter stehen — das passiert nur
        // ausserhalb eines Portals (zentrale Domain), wo die Seite nicht laeuft.
        return $tenant instanceof Tenant
            ? $this->branding->resolveLegalPlaceholders($html, $tenant)
            : $html;
    }

    /**
     * Steht AdSense schon im gepflegten Text? Gesucht wird das Wort, nicht
     * eine Ueberschrift: ein Portal, das den Abschnitt irgendwann selbst
     * eingepflegt hat, soll ihn nicht doppelt bekommen.
     */
    private function alreadyCovered(string $html): bool
    {
        return str_contains(mb_strtolower($html), 'adsense');
    }
}
