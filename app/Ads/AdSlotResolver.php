<?php

declare(strict_types=1);

namespace App\Ads;

use App\Ads\Dto\AdRenderContext;
use App\Ads\Dto\AdResolution;
use App\Ads\Dto\RenderedAd;
use App\Ads\Enums\AdDevice;
use App\Ads\Enums\AdPageType;
use App\Ads\Models\AdPlacement;
use App\Ads\Models\AdSetting;
use App\Ads\Models\AdUnit;
use App\Ads\Support\AdCache;
use App\Ads\Support\AdSlotCatalog;
use App\Models\Tenant;
use Illuminate\Support\Facades\Cache;

/**
 * Welche Belegung laeuft in diesem Anker? (docs/ad-slots.md 8, Ticket #4)
 *
 * Aufgeloest wird **pro Seitentyp und Portal**, nicht pro Anker: eine
 * Portalseite hat bis zu sechs Anker, und sechs Abfragen auf die zentrale
 * Datenbank pro Aufruf sind nicht tragbar. Eine Abfrage fuellt die Karte
 *
 *     [slotKey][geraet] => AdResolution
 *
 * fuer den ganzen Seitentyp; sie liegt eine Stunde im Cache (AdCache) und
 * zusaetzlich fuer die Dauer der Anfrage in dieser Instanz (scoped gebunden).
 *
 * Der Schluessel `all` heisst "laeuft auf beiden Geraeten" und ist nur
 * besetzt, wenn der Katalog-Anker `devices: all` zulaesst **und** die
 * gewinnende Belegung `device = all` hat (Ticket #20). Auf einem
 * gerätegebundenen Anker — oder wenn eine gerätegebundene Belegung die
 * hoehere Prioritaet hat — bleibt `all` leer; dann ist je Geraet zu fragen
 * (`mobile`/`desktop`), wie es die Komponente (#5) tut. Lieber ein leerer
 * Anker als eine Anzeige auf dem falschen Geraet: bei AdSense ist das eine
 * falsche Impression.
 *
 * Im Cache liegt nur die **Aufloesung**, nie gerendertes HTML: Einwilligung,
 * Editor-Modus und Publisher-ID wechseln je Anfrage und duerfen nicht
 * eingefroren werden. Gerendert wird deshalb bei jedem Aufruf.
 *
 * Nichts wird ausgespielt, wenn
 * * das Portal keine Werbung freigegeben hat (`ad_settings.ads_enabled`,
 *   fehlende Zeile gilt als aus),
 * * keine aktive Belegung fuer den Anker existiert,
 * * der Zeitraum nicht laeuft (`starts_at`/`ends_at`),
 * * die Geraeteklasse nicht passt,
 * * das Werbemittel abgeschaltet ist oder sein Provider nicht registriert ist,
 * * oder der Provider die harten Voraussetzungen nicht erfuellt
 *   (AdSense ohne Publisher-ID, HTML ohne Groesse).
 */
final class AdSlotResolver
{
    /**
     * Ergebnis je Anfrage, Schluessel "{tenantId}:{pageType}".
     *
     * @var array<string, array<string, mixed>>
     */
    private array $memo = [];

    public function __construct(private readonly AdProviderRegistry $providers) {}

    /**
     * Gerendertes Werbemittel fuer einen Anker; null, wenn der Anker leer
     * bleibt. Ohne Kontext wird einer aus Katalog und Portal-Einstellungen
     * gebaut ({@see context()}).
     */
    public function resolve(
        string $slotKey,
        Tenant|int|string|null $tenant = null,
        AdDevice $device = AdDevice::ALL,
        ?AdRenderContext $context = null,
    ): ?RenderedAd {
        $tenantId = $this->tenantId($tenant);

        if ($tenantId === null) {
            return null;
        }

        $context ??= $this->context($slotKey, $tenant, $device);
        $resolution = $this->resolution($slotKey, $tenant, $device, $context->editorMode);

        if ($resolution === null) {
            return null;
        }

        $provider = $this->providers->get($resolution->provider);

        if ($provider === null) {
            return null;
        }

        $rendered = $provider->render($resolution->unit(), $context);

        if ($rendered === null) {
            return null;
        }

        // Die Belegung kennt nur der Resolver — der Provider sieht sie nicht.
        return new RenderedAd(
            html: $rendered->html,
            headDependencies: $rendered->headDependencies,
            providerKey: $rendered->providerKey,
            adUnitId: $rendered->adUnitId,
            placementId: $resolution->placementId,
            meta: $rendered->meta + [
                'ad_unit_name' => $resolution->adUnitName,
                'priority' => $resolution->priority,
                'device' => $resolution->device->value,
            ],
            // Muss mitwandern: ohne sie wuerde der Sandbox-Rahmen des
            // HtmlProviders auf eine Einwilligung warten, die er nicht
            // braucht (#9).
            requiresConsent: $rendered->requiresConsent,
        );
    }

    /**
     * Die gueltige Belegung eines Ankers, ohne zu rendern — fuer den Editor
     * (#6/#7) und `ads:resolve`.
     */
    public function resolution(
        string $slotKey,
        Tenant|int|string|null $tenant = null,
        AdDevice $device = AdDevice::ALL,
        bool $fresh = false,
    ): ?AdResolution {
        $pageType = AdSlotCatalog::pageTypeOf($slotKey);

        if ($pageType === null) {
            return null;
        }

        $map = $this->forPageType($pageType, $tenant, $fresh);

        $data = $map['slots'][$slotKey][$device->value] ?? null;

        return is_array($data) ? AdResolution::fromArray($data) : null;
    }

    /**
     * Die ganze Karte eines Seitentyps — Freigabe, Publisher-ID und die
     * Belegungen je Anker und Geraet.
     *
     * @return array{ads_enabled: bool, publisher_id: string|null, auto_ads: bool, slots: array<string, array<string, array<string, mixed>>>}
     */
    public function forPageType(AdPageType $pageType, Tenant|int|string|null $tenant = null, bool $fresh = false): array
    {
        $tenantId = $this->tenantId($tenant);

        if ($tenantId === null) {
            return $this->emptyMap();
        }

        // Im Editor-Modus wird nicht gecacht, weder gelesen noch geschrieben —
        // sonst zeigt die Vorschau einen alten Stand (docs/ad-slots.md 8).
        if ($fresh) {
            return $this->build($pageType, $tenantId);
        }

        $memoKey = "{$tenantId}:{$pageType->value}";

        if (isset($this->memo[$memoKey])) {
            return $this->memo[$memoKey];
        }

        return $this->memo[$memoKey] = Cache::remember(
            AdCache::key($tenantId, $pageType),
            AdCache::ttl(),
            fn (): array => $this->build($pageType, $tenantId),
        );
    }

    /**
     * Kontext fuer den Provider: Mindesthoehen aus dem Katalog, Publisher-ID
     * aus den Portal-Einstellungen. Die Komponente (#5) baut ihn mit den
     * echten Werten fuer Einwilligung, Editor-Modus und Lazy-Loading.
     */
    public function context(
        string $slotKey,
        Tenant|int|string|null $tenant = null,
        AdDevice $device = AdDevice::ALL,
        bool $consentGranted = false,
        bool $editorMode = false,
        bool $lazy = true,
    ): AdRenderContext {
        $tenantId = $this->tenantId($tenant);
        $pageType = AdSlotCatalog::pageTypeOf($slotKey);

        $publisherId = null;

        if ($tenantId !== null && $pageType !== null) {
            $publisherId = $this->forPageType($pageType, $tenant, $editorMode)['publisher_id'] ?? null;
        }

        return AdRenderContext::forSlot(
            slotKey: $slotKey,
            device: $device,
            tenantId: $tenantId,
            adsensePublisherId: $publisherId,
            consentGranted: $consentGranted,
            editorMode: $editorMode,
            lazy: $lazy,
        );
    }

    /** Hat das Portal Werbung freigegeben (`ad_settings.ads_enabled`)? */
    public function adsEnabled(Tenant|int|string|null $tenant = null): bool
    {
        $tenantId = $this->tenantId($tenant);

        if ($tenantId === null) {
            return false;
        }

        return (bool) ($this->forPageType(AdPageType::HOME, $tenant)['ads_enabled'] ?? false);
    }

    /**
     * Spielt dieses Portal AdSense Auto Ads aus? Nur wahr, wenn auch der
     * Hauptschalter steht — `adsense_auto_ads` allein wirkt nicht
     * (docs/ad-slots.md 1, Ticket #18).
     */
    public function autoAdsEnabled(Tenant|int|string|null $tenant = null): bool
    {
        $tenantId = $this->tenantId($tenant);

        if ($tenantId === null) {
            return false;
        }

        $map = $this->forPageType(AdPageType::HOME, $tenant);

        return (bool) ($map['ads_enabled'] ?? false) && (bool) ($map['auto_ads'] ?? false);
    }

    /** Karte dieser Anfrage wegwerfen — nach einem Portalwechsel im Prozess. */
    public function flushMemo(): void
    {
        $this->memo = [];
    }

    /**
     * Die eine Abfrage je Seitentyp und Portal.
     *
     * @return array{ads_enabled: bool, publisher_id: string|null, auto_ads: bool, slots: array<string, array<string, array<string, mixed>>>}
     */
    private function build(AdPageType $pageType, int|string $tenantId): array
    {
        $setting = AdSetting::query()->forTenant($tenantId)->first();
        $map = [
            'ads_enabled' => (bool) $setting?->ads_enabled,
            'publisher_id' => $setting?->adsensePublisherId(),
            // Auto Ads sind kein Anker, sondern eine Einstellung des Portals
            // (docs/ad-slots.md 1, Ticket #18). Sie reiten hier mit, damit das
            // Layout dafuer keine zweite Abfrage auf die zentrale Datenbank
            // braucht — gelesen wird der Wert ueber {@see autoAds()}.
            'auto_ads' => (bool) $setting?->adsense_auto_ads,
            'slots' => [],
        ];

        // Fehlt die Zeile oder steht der Schalter aus, bleibt das Portal leer —
        // egal welche Belegungen netzweit aktiv sind.
        if (! $map['ads_enabled']) {
            return $map;
        }

        $placements = AdPlacement::query()
            ->with('adUnit')
            ->deliverable()
            ->forTenant($tenantId)
            ->where('page_type', $pageType->value)
            ->resolutionOrder()
            ->get();

        foreach (array_keys(AdSlotCatalog::forPageType($pageType)) as $slotKey) {
            $candidates = $placements->filter(function (AdPlacement $placement) use ($slotKey): bool {
                $unit = $placement->adUnit;

                return $placement->slot_key === $slotKey
                    && $unit instanceof AdUnit
                    // Ein Werbemittel, dessen Provider nicht (mehr)
                    // registriert ist, wird uebergangen — dann gewinnt die
                    // naechste Belegung in der Rangfolge.
                    && $this->providers->has((string) $unit->provider);
            });

            if ($candidates->isEmpty()) {
                continue;
            }

            $slotDevice = AdSlotCatalog::deviceOf($slotKey);

            foreach ([AdDevice::ALL, AdDevice::MOBILE, AdDevice::DESKTOP] as $device) {
                $winner = $candidates->first(
                    fn (AdPlacement $placement) => $device === AdDevice::ALL
                        || $this->deviceOf($placement) === AdDevice::ALL
                        || $this->deviceOf($placement) === $device,
                );

                if ($winner === null) {
                    continue;
                }

                // Unter `all` steht nur, was auf beiden Geraeten laufen darf
                // (Ticket #20): der Anker muss `devices: all` sein **und** die
                // gewinnende Belegung `device = all`. Sonst bleibt der
                // Eintrag leer, und wer ohne Geraeteangabe fragt, spielt
                // nichts aus statt das Falsche — eine Ausspielung ausserhalb
                // der gebuchten Geraeteklasse ist bei AdSense eine falsche
                // Impression. Gerade auf Ankern mit `devices: all` kann je
                // Geraet eine andere Belegung gewinnen; abzufragen ist dort
                // zweimal, `mobile` und `desktop` (docs/ad-slots.md 8).
                if ($device === AdDevice::ALL
                    && ($slotDevice !== AdDevice::ALL || $this->deviceOf($winner) !== AdDevice::ALL)) {
                    continue;
                }

                $map['slots'][$slotKey][$device->value] = $this->toResolution($winner)->toArray();
            }
        }

        return $map;
    }

    /** Geraetewahl einer Belegung; ein fehlender Cast gilt als ALL. */
    private function deviceOf(AdPlacement $placement): AdDevice
    {
        return $placement->device instanceof AdDevice ? $placement->device : AdDevice::ALL;
    }

    private function toResolution(AdPlacement $placement): AdResolution
    {
        /** @var AdUnit $unit */
        $unit = $placement->adUnit;

        return new AdResolution(
            placementId: (int) $placement->getKey(),
            adUnitId: (int) $unit->getKey(),
            adUnitName: (string) $unit->name,
            provider: (string) $unit->provider,
            config: (array) $unit->config_json,
            device: $this->deviceOf($placement),
            priority: (int) $placement->priority,
        );
    }

    /**
     * @return array{ads_enabled: bool, publisher_id: null, auto_ads: bool, slots: array<string, never>}
     */
    private function emptyMap(): array
    {
        return ['ads_enabled' => false, 'publisher_id' => null, 'auto_ads' => false, 'slots' => []];
    }

    private function tenantId(Tenant|int|string|null $tenant): int|string|null
    {
        if ($tenant instanceof Tenant) {
            return $tenant->getKey();
        }

        if ($tenant !== null && $tenant !== '') {
            return $tenant;
        }

        return tenant()?->getKey();
    }
}
