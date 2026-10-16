<?php

declare(strict_types=1);

namespace App\Ads\Preview;

use App\Ads\AdSlotResolver;
use App\Ads\Enums\AdDevice;
use App\Ads\Enums\AdPageType;
use App\Ads\Support\AdSlotCatalog;
use App\Ads\Support\AdThemeContext;
use App\Models\Tenant;

/**
 * Die Ankerliste neben der Vorschau (Ticket #7).
 *
 * Eine Zeile je Anker des Seitentyps, in Katalogreihenfolge (`sort`), mit dem
 * Zustand fuer das gerade gezeigte Geraet. Drei Regeln stecken darin:
 *
 * * **Nur Anker des aktiven Themes.** Der Katalog fuehrt je Anker die Themes,
 *   in deren Views er steckt ({@see AdSlotCatalog::forPageType()} mit Theme).
 *   Ohne diese Einschraenkung waere eine Position waehlbar und stumm
 *   (Ticket #14).
 * * **Je Geraet aufloesen, nie ueber `all`.** Auf einem Anker mit
 *   `devices: all` kann fuer Mobil eine andere Belegung gewinnen als fuer
 *   Desktop; der Schluessel `all` in der Resolver-Karte ist nur besetzt, wenn
 *   Anker **und** Belegung `all` sind (Ticket #20). Gefragt wird deshalb immer
 *   mit dem konkreten Geraet und auf `all` nur zurueckgefallen.
 * * **Ohne Cache.** Im Editor zaehlt der Stand von jetzt, nicht der von vor
 *   fuenf Minuten (docs/ad-slots.md 8) — darum `fresh: true`.
 * * **Route ist eine eigene Dimension.** Ein Seitentyp buendelt mehrere
 *   Routen, aber nicht jeder Anker steht auf jeder — `guide_index_sidebar_sticky`
 *   nur auf der Ratgeber-Kategorie, nicht auf /ratgeber (Ticket #27). Die
 *   Zeile bleibt in der Liste, traegt aber `on_route: false` und die Routen,
 *   auf denen der Anker gerendert wird: ein verschwundener Anker waere nicht
 *   erklaerbar, ein stummer ohne Kennzeichnung eine Falle.
 */
final readonly class EditorSlotList
{
    public function __construct(private AdSlotResolver $resolver) {}

    /**
     * @param  string|null  $route  Route der gezeigten Seite; null bewertet
     *                              keine Route und meldet jede Zeile als
     *                              gerendert.
     * @return list<array{key: string, label: string, devices: string, allows_device: bool, min_height: int, filled: bool, placement_id: int|null, unit_name: string|null, priority: int|null, on_route: bool, route_labels: list<string>}>
     */
    public function build(Tenant $tenant, AdPageType $pageType, AdDevice $device, ?string $route = null): array
    {
        $theme = AdThemeContext::forTenant($tenant);
        $karte = $this->resolver->forPageType($pageType, $tenant, fresh: true);

        $zeilen = [];

        foreach (AdSlotCatalog::forPageType($pageType, $theme) as $slot) {
            $key = (string) ($slot['key'] ?? '');

            if ($key === '') {
                continue;
            }

            $belegung = $this->belegung($karte['slots'][$key] ?? [], $device);

            $zeilen[] = [
                'key' => $key,
                'label' => (string) ($slot['label'] ?? $key),
                'devices' => AdSlotCatalog::deviceOf($key)->value,
                'allows_device' => AdSlotCatalog::allowsDevice($key, $device),
                'on_route' => $route === null || AdSlotCatalog::supportsRoute($key, $route),
                'route_labels' => AdSlotCatalog::routeLabelsOf($key),
                'min_height' => $this->mindesthoehe($slot, $device),
                'filled' => $belegung !== null,
                'placement_id' => $belegung === null ? null : (int) $belegung['placement_id'],
                'unit_name' => $belegung === null ? null : (string) $belegung['ad_unit_name'],
                'priority' => $belegung === null ? null : (int) $belegung['priority'],
            ];
        }

        return $zeilen;
    }

    /** Hat das Portal fuer diesen Seitentyp ueberhaupt Anker? */
    public function hasSlots(Tenant $tenant, AdPageType $pageType): bool
    {
        return AdSlotCatalog::forPageType($pageType, AdThemeContext::forTenant($tenant)) !== [];
    }

    /**
     * @param  array<string, array<string, mixed>>  $slotKarte
     * @return array<string, mixed>|null
     */
    private function belegung(array $slotKarte, AdDevice $device): ?array
    {
        return $slotKarte[$device->value] ?? $slotKarte[AdDevice::ALL->value] ?? null;
    }

    /**
     * @param  array<string, mixed>  $slot
     */
    private function mindesthoehe(array $slot, AdDevice $device): int
    {
        $schluessel = $device === AdDevice::DESKTOP ? 'min_height_desktop' : 'min_height_mobile';

        return (int) ($slot[$schluessel] ?? config("ad-slots.defaults.{$schluessel}", 0));
    }
}
