<?php

declare(strict_types=1);

namespace App\Ads;

use App\Ads\Dto\LegacyAdSettingPlan;
use App\Ads\Dto\LegacyAdUnitPlan;
use App\Ads\Dto\LegacyTakeoverPlan;
use App\Ads\Enums\AdDevice;
use App\Ads\Enums\AdPlacementStatus;
use App\Ads\Enums\AdProviderType;
use App\Ads\Models\AdPlacement;
use App\Ads\Models\AdSetting;
use App\Ads\Models\AdUnit;
use App\Ads\Support\AdSlotCatalog;
use App\Models\Tenant;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Uebernahme des Altbestands in das neue Werbeplatz-System (Ticket #15).
 *
 * Das Altsystem haelt je Portal eigene Zeilen in der Tenant-Tabelle
 * `ad_slots` mit rohem AdSense-Markup in `code` (App\Models\Portal\AdSlot).
 * Das neue System haelt Werbemittel und Belegungen zentral. Diese Klasse
 * liest den Bestand, bildet ihn auf den Katalog ab und schreibt ihn — in zwei
 * Schritten, damit der Trockenlauf genau das zeigt, was der Ernstfall tut.
 *
 * Drei Entscheidungen, die hier und nicht im Kommando stehen:
 *
 * 1. **Provider `adsense`, nicht `html`.** Aus dem Markup werden
 *    Anzeigenblock (`data-ad-slot`), Layout-Schluessel und Format gezogen;
 *    das rohe Skript wird verworfen. Als `html` uebernommen waere der
 *    Bestand schneller drin, liefe aber ohne Einwilligungs-Gate (#9), ohne
 *    Entdoppelung des Loaders und ohne Hoehenreservierung.
 * 2. **Die Publisher-ID wandert nach `ad_settings`**, nicht ins Werbemittel:
 *    `data-ad-client` kommt im neuen System immer aus den Einstellungen des
 *    Portals (AdSenseProvider). Im Netz gibt es zwei AdSense-Konten.
 * 3. **Gebuendelt wird nach Anker, Geraet und Anzeigenblock.** Der Alt-Code
 *    ist je Position und Konto gleich; aus den 118 Zeilen werden darum wenige
 *    Werbemittel mit je einer Belegung und einem Portal-Scope im Pivot.
 *
 * Idempotent: ein zweiter Lauf erkennt Werbemittel am Anzeigenblock und
 * Belegungen an (Werbemittel, Anker, Geraet) wieder und legt nichts doppelt
 * an. Bereits gepflegte zentrale Werte (Publisher-ID, ads.txt) werden nie
 * ueberschrieben, sondern gemeldet.
 */
final class LegacyAdTakeover
{
    /**
     * Alt-Position (config/ad-positions.php) => Katalogschluessel
     * (config/ad-slots.php). `null` = bewusst keine Uebernahme.
     *
     * Abgebildet sind die Positionen, die im Netz tatsaechlich belegt sind
     * (gemessen: 118 Zeilen in 17 Portalen, 7 Positionen). Alle liegen auf
     * dem Firmenprofil, deshalb sind alle Ziele `company_*`. Die Reihenfolge
     * folgt dem Markup der Themes `default`/`starter`:
     *
     *   after_breadcrumb (ueber der Kopf-Karte) -> company_after_header_card
     *   content_before_intro (vor dem Einleitungstext) -> company_after_about
     *   after_photos (nach den Fotos) -> company_after_reviews
     *   after_change_request (letzter Inhaltsblock) -> company_before_footer
     *   sidebar_top (Seitenspalte oben) -> company_sidebar_sticky
     *   sidebar_after_claim (Seitenspalte unter dem Claim) -> company_sidebar_top
     *
     * Beide Seitenspalten-Positionen werden uebernommen, seit der Katalog v3
     * auf dem Profil einen zweiten Seitenspalten-Anker fuehrt
     * (`company_sidebar_top`, Ticket #19). Vorher fiel `sidebar_after_claim`
     * weg — 16 aktive Plaetze —, weil eine zweite Belegung desselben Ankers
     * wirkungslos ist: der Resolver spielt je Anker und Geraet nur eine aus.
     * Die Zuordnung ist bewusst nicht nach Hoehe sortiert: `sidebar_top` bleibt
     * auf `company_sidebar_sticky`, damit die in #15 schon geschriebenen
     * Belegungen stehen bleiben und ein zweiter Lauf idempotent ist.
     *
     * Bewusst ohne Entsprechung:
     *
     * * `auto_ads` — kein Anker, sondern eine Einstellung je Portal
     *   (docs/ad-slots.md Abschnitt 1). Wandert nach
     *   `ad_settings.adsense_auto_ads`, siehe {@see self::AUTO_ADS_POSITION}.
     * * `mobile_sticky_bottom` — Overlay statt Anker, in v1 bewusst draussen
     *   (Abschnitt 1). Im Bestand ohnehin in keinem Portal belegt.
     *
     * Eine Position, die hier gar nicht steht, wird gemeldet und nicht
     * geraten.
     */
    public const SLOT_MAP = [
        'after_breadcrumb' => 'company_after_header_card',
        'content_before_intro' => 'company_after_about',
        'after_photos' => 'company_after_reviews',
        'after_change_request' => 'company_before_footer',
        'sidebar_top' => 'company_sidebar_sticky',
        'sidebar_after_claim' => 'company_sidebar_top',
        'mobile_sticky_bottom' => null,
    ];

    /** Alt-Position, die nur den AdSense-Loader enthaelt (Auto Ads). */
    public const AUTO_ADS_POSITION = 'auto_ads';

    /**
     * Prioritaet der uebernommenen Belegungen. Bewusst niedrig: eine spaeter
     * in #8 angelegte Belegung soll den Bestand ohne Umweg ueberstimmen
     * koennen (docs/ad-slots.md 4.3).
     */
    public const PRIORITY = 10;

    /**
     * Liest den Bestand und baut den Plan. Schreibt nichts.
     *
     * @param  list<string>  $tenantIdentifiers  UUID, ID oder Domain; leer = alle Portale
     */
    public function plan(array $tenantIdentifiers = [], bool $enableAds = true): LegacyTakeoverPlan
    {
        /** @var array<string, array<string, mixed>> $groups */
        $groups = [];
        $settings = [];
        $notes = [];
        $seen = 0;
        $taken = 0;

        foreach ($this->tenants($tenantIdentifiers) as $tenant) {
            try {
                $legacy = $this->readLegacy($tenant);
            } catch (Throwable $e) {
                $notes[] = [
                    'tenant' => (string) $tenant->name,
                    'position' => '—',
                    'reason' => "Portal-Datenbank nicht lesbar: {$e->getMessage()}",
                ];

                continue;
            }

            $publishers = [];
            $autoAds = false;
            $tenantTaken = 0;

            foreach ($legacy['slots'] as $slot) {
                $seen++;
                $position = (string) $slot->position;
                $publisher = $this->parsePublisherId((string) $slot->code);

                if ($publisher !== null) {
                    $publishers[$publisher] = ($publishers[$publisher] ?? 0) + 1;
                }

                if (! $slot->is_active) {
                    $notes[] = $this->note($tenant, $position, 'Alt-Slot ist abgeschaltet — nicht uebernommen.');

                    continue;
                }

                if ($position === self::AUTO_ADS_POSITION) {
                    $autoAds = true;
                    $taken++;

                    continue;
                }

                if (! array_key_exists($position, self::SLOT_MAP)) {
                    $notes[] = $this->note($tenant, $position, 'Position steht nicht in der Abbildung — Handarbeit noetig.');

                    continue;
                }

                $slotKey = self::SLOT_MAP[$position];

                if ($slotKey === null) {
                    $notes[] = $this->note($tenant, $position, 'Bewusst ohne Entsprechung im Katalog v1 (siehe LegacyAdTakeover::SLOT_MAP).');

                    continue;
                }

                if (! AdSlotCatalog::exists($slotKey)) {
                    $notes[] = $this->note($tenant, $position, "Zielanker {$slotKey} steht nicht im Katalog.");

                    continue;
                }

                $slotId = $this->parseSlotId((string) $slot->code);

                if ($slotId === null || $publisher === null) {
                    $notes[] = $this->note($tenant, $position, 'Kein AdSense-Anzeigenblock im Code — als eigenes HTML von Hand anzulegen (#10).');

                    continue;
                }

                $wanted = $this->parseDevice($slot->device_visibility);
                $device = $wanted->narrowTo(AdSlotCatalog::deviceOf($slotKey));

                // Der Anker verengt die Geraetewahl (z. B. ist
                // company_after_header_card nur mobil): das ist ein echter
                // Verlust gegenueber dem Altsystem und gehoert in die
                // Abnahmeliste, nicht nur in die Tabelle der Belegungen.
                if ($device !== $wanted) {
                    $notes[] = $this->note(
                        $tenant,
                        $position,
                        "Geraetewahl von {$wanted->value} auf {$device->value} verengt — der Anker {$slotKey} laesst nur das zu.",
                    );
                }

                $layoutKey = $this->parseLayoutKey((string) $slot->code);
                $format = $this->parseFormat((string) $slot->code, $layoutKey);
                $key = implode('|', [$slotKey, $device->value, $publisher, $slotId, (string) $layoutKey, $format]);

                $groups[$key] ??= [
                    'position' => $position,
                    'slot_key' => $slotKey,
                    'device' => $device,
                    'publisher' => $publisher,
                    'slot_id' => $slotId,
                    'layout_key' => $layoutKey,
                    'format' => $format,
                    'tenant_ids' => [],
                    'tenant_names' => [],
                ];

                $groups[$key]['tenant_ids'][] = (int) $tenant->getKey();
                $groups[$key]['tenant_names'][] = (string) $tenant->name;
                $taken++;
                $tenantTaken++;
            }

            if (count($publishers) > 1) {
                $notes[] = $this->note(
                    $tenant,
                    '—',
                    'Mehrere AdSense-Konten im Alt-Code: '.implode(', ', array_keys($publishers)).' — es wird das haeufigste uebernommen.',
                );
            }

            $publisherId = $this->mostUsed($publishers);
            $adsTxt = $legacy['ads_txt'];

            if ($publisherId === null && ! $autoAds && $adsTxt === null && $tenantTaken === 0) {
                continue;
            }

            $settings[] = new LegacyAdSettingPlan(
                tenantId: (int) $tenant->getKey(),
                tenantName: (string) $tenant->name,
                publisherId: $publisherId,
                autoAds: $autoAds,
                adsTxt: $adsTxt,
                enableAds: $enableAds && ($tenantTaken > 0 || $autoAds),
            );
        }

        $units = array_values(array_map(
            fn (array $group) => new LegacyAdUnitPlan(
                legacyPosition: $group['position'],
                slotKey: $group['slot_key'],
                device: $group['device'],
                publisherId: $group['publisher'],
                slotId: $group['slot_id'],
                layoutKey: $group['layout_key'],
                format: $group['format'],
                tenantIds: $group['tenant_ids'],
                tenantNames: $group['tenant_names'],
            ),
            $groups,
        ));

        return new LegacyTakeoverPlan($units, $settings, $notes, $seen, $taken);
    }

    /**
     * Schreibt den Plan in die Central-DB. Ein zweiter Lauf mit demselben
     * Plan aendert nichts mehr.
     *
     * @return array{counts: array<string, int>, messages: list<string>}
     */
    public function apply(LegacyTakeoverPlan $plan, ?int $userId = null): array
    {
        $counts = [
            'units_created' => 0,
            'units_reused' => 0,
            'placements_created' => 0,
            'placements_reused' => 0,
            'tenants_attached' => 0,
            'settings_written' => 0,
        ];
        $messages = [];

        DB::connection((new AdUnit)->getConnectionName())->transaction(
            function () use ($plan, $userId, &$counts, &$messages): void {
                foreach ($plan->units as $unit) {
                    $model = $this->findUnit($unit);

                    if ($model === null) {
                        $model = AdUnit::create([
                            'name' => $unit->unitName(),
                            'provider' => AdProviderType::ADSENSE->value,
                            'config_json' => $unit->unitConfig(),
                            'is_active' => true,
                        ]);
                        $counts['units_created']++;
                    } else {
                        $counts['units_reused']++;
                    }

                    $placement = AdPlacement::query()
                        ->where('ad_unit_id', $model->getKey())
                        ->where('slot_key', $unit->slotKey)
                        ->where('device', $unit->device->value)
                        ->first();

                    if ($placement === null) {
                        $placement = AdPlacement::create([
                            'ad_unit_id' => $model->getKey(),
                            'slot_key' => $unit->slotKey,
                            'device' => $unit->device->value,
                            'status' => AdPlacementStatus::ACTIVE->value,
                            'priority' => self::PRIORITY,
                            'applies_to_all_tenants' => false,
                            'created_by' => $userId,
                        ]);
                        $counts['placements_created']++;
                    } else {
                        $counts['placements_reused']++;
                    }

                    // syncWithoutDetaching: ein Lauf fuer ein einzelnes Portal
                    // (#11-Pilot) darf den Scope der anderen nicht abraeumen.
                    $changes = $placement->tenants()->syncWithoutDetaching($unit->tenantIds);
                    $counts['tenants_attached'] += count($changes['attached']);
                }

                foreach ($plan->settings as $setting) {
                    $messages = array_merge($messages, $this->writeSetting($setting, $userId, $counts));
                }
            },
        );

        return ['counts' => $counts, 'messages' => $messages];
    }

    /**
     * Eine Zeile `ad_settings` (central) nachziehen. Vorhandene Werte bleiben
     * stehen — die zentrale Pflege (#10) ist juenger als der Altbestand.
     *
     * @param  array<string, int>  $counts
     * @return list<string>
     */
    private function writeSetting(LegacyAdSettingPlan $setting, ?int $userId, array &$counts): array
    {
        $messages = [];

        $model = AdSetting::query()->firstOrNew(['tenant_id' => $setting->tenantId]);

        if (! $model->exists) {
            $model->fill(AdSetting::defaults());
        }

        if ($setting->publisherId !== null) {
            if (! $model->hasOwnPublisherId()) {
                $model->adsense_publisher_id = $setting->publisherId;
            } elseif (trim((string) $model->adsense_publisher_id) !== $setting->publisherId) {
                $messages[] = "{$setting->tenantName}: Publisher-ID bleibt bei {$model->adsense_publisher_id}, Alt-Code nennt {$setting->publisherId}.";
            }
        }

        if ($setting->autoAds) {
            $model->adsense_auto_ads = true;
        }

        if ($setting->adsTxt !== null) {
            if (trim((string) $model->ads_txt_extra) === '') {
                $model->ads_txt_extra = $setting->adsTxt;
            } else {
                $messages[] = "{$setting->tenantName}: ads.txt-Zusatzzeilen sind zentral schon gepflegt — Tenant-Fassung nicht uebernommen.";
            }
        }

        if ($setting->enableAds) {
            $model->ads_enabled = true;
        }

        if (! $model->exists || $model->isDirty()) {
            $model->updated_by = $userId;
            $model->save();
            $counts['settings_written']++;
        }

        return $messages;
    }

    /**
     * Werbemittel wiedererkennen: Provider, Anzeigenblock, Layout-Schluessel
     * und Format. Gefiltert wird in PHP — es sind wenige Zeilen, und ein
     * JSON-Pfad in der WHERE-Klausel ist je MySQL-Version verschieden.
     */
    private function findUnit(LegacyAdUnitPlan $unit): ?AdUnit
    {
        return AdUnit::query()
            ->where('provider', AdProviderType::ADSENSE->value)
            ->get()
            ->first(function (AdUnit $candidate) use ($unit): bool {
                $config = (array) $candidate->config_json;

                return (string) ($config['slot_id'] ?? '') === $unit->slotId
                    && (string) ($config['layout_key'] ?? '') === (string) $unit->layoutKey
                    && (string) ($config['format'] ?? 'responsive') === $unit->format;
            });
    }

    /**
     * @param  list<string>  $identifiers
     * @return list<Tenant>
     */
    private function tenants(array $identifiers): array
    {
        $query = Tenant::query()
            ->when($identifiers !== [], fn ($query) => $query->where(function ($q) use ($identifiers): void {
                $q->whereIn('uuid', $identifiers)
                    ->orWhereIn('domain', $identifiers)
                    ->orWhereIn('id', array_filter($identifiers, 'is_numeric'));
            }))
            ->orderBy('id');

        $tenants = [];

        foreach ($query->cursor() as $tenant) {
            $tenants[] = $tenant;
        }

        return $tenants;
    }

    /**
     * Alt-Slots und ads.txt eines Portals aus der Tenant-DB.
     *
     * Gelesen wird ueber den Query Builder und nicht ueber die Alt-Modelle:
     * die Tabellen verschwinden mit #11, und die Uebernahme braucht genau
     * diese sechs Spalten — nicht Casts, Scopes und ULIDs eines Modells, das
     * danach geloescht wird.
     *
     * @return array{slots: \Illuminate\Support\Collection<int, object>, ads_txt: string|null}
     */
    private function readLegacy(Tenant $tenant): array
    {
        return $tenant->run(function (): array {
            $adsTxt = DB::connection('tenant')->table('ad_settings')->value('ads_txt_content');

            $slots = DB::connection('tenant')->table('ad_slots')
                ->select(['position', 'code', 'is_active', 'device_visibility'])
                ->orderBy('position')
                ->orderBy('sort_order')
                ->get();

            return [
                'slots' => $slots,
                'ads_txt' => trim((string) $adsTxt) === '' ? null : (string) $adsTxt,
            ];
        });
    }

    private function parsePublisherId(string $code): ?string
    {
        return preg_match('/ca-pub-\d{10,20}/', $code, $m) === 1 ? $m[0] : null;
    }

    private function parseSlotId(string $code): ?string
    {
        return preg_match('/data-ad-slot\s*=\s*["\'](\d{6,20})["\']/', $code, $m) === 1 ? $m[1] : null;
    }

    private function parseLayoutKey(string $code): ?string
    {
        return preg_match('/data-ad-layout-key\s*=\s*["\']([^"\']+)["\']/', $code, $m) === 1 ? $m[1] : null;
    }

    /**
     * Formatschluessel des Katalogs aus `data-ad-format`. `fluid` ist bei
     * AdSense In-Feed **und** In-Article; der Layout-Schluessel kommt nur bei
     * In-Feed vor und entscheidet deshalb.
     */
    private function parseFormat(string $code, ?string $layoutKey): string
    {
        if (preg_match('/data-ad-format\s*=\s*["\']([^"\']+)["\']/', $code, $m) !== 1) {
            return 'responsive';
        }

        return match (strtolower(trim($m[1]))) {
            'fluid' => $layoutKey === null ? 'in_article' : 'in_feed',
            default => 'responsive',
        };
    }

    /**
     * `ad_slots.device_visibility` ist ein JSON-Array mit bis zu drei Werten;
     * "tablet" faellt weg (AdDevice kennt nur mobile/desktop/all).
     */
    private function parseDevice(mixed $visibility): AdDevice
    {
        $values = is_string($visibility) ? (json_decode($visibility, true) ?: []) : (array) $visibility;
        $values = array_map(fn ($value) => strtolower((string) $value), (array) $values);

        $desktop = in_array('desktop', $values, true);
        $mobile = in_array('mobile', $values, true) || in_array('tablet', $values, true);

        return match (true) {
            $desktop && ! $mobile => AdDevice::DESKTOP,
            $mobile && ! $desktop => AdDevice::MOBILE,
            default => AdDevice::ALL,
        };
    }

    /**
     * @param  array<string, int>  $counted
     */
    private function mostUsed(array $counted): ?string
    {
        if ($counted === []) {
            return null;
        }

        arsort($counted);

        return (string) array_key_first($counted);
    }

    /**
     * @return array{tenant: string, position: string, reason: string}
     */
    private function note(Tenant $tenant, string $position, string $reason): array
    {
        return [
            'tenant' => (string) $tenant->name,
            'position' => $position,
            'reason' => $reason,
        ];
    }
}
