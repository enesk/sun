<?php

declare(strict_types=1);

namespace App\Content\Services;

use App\Content\Enums\DisplayStatus;
use App\Content\Enums\SourceFrequency;
use App\Content\Jobs\RunSourceConnectorJob;
use App\Content\Models\Central\ProviderState;
use App\Content\Models\Central\SourceSetting;
use App\Content\Models\SourceItem;
use App\Content\Sources\SourceRegistry;
use App\Models\Tenant;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

/**
 * Quellen-Monitor (#20), design/content-dashboard.md, §6.
 *
 * Beantwortet: welche Quelle liefert nicht, seit wann, und was fehlt dadurch?
 * Die Zustaende stehen in `provider_states` (central, #7), die Zahl der
 * gelieferten Signale in `source_items` je Mandantendatenbank.
 *
 * Die Signalzahlen werden 60 Sekunden zwischengespeichert: 24 Portale
 * einzeln zu zaehlen ist teuer, und der Monitor wird anlassbezogen geoeffnet,
 * nicht dauerhaft beobachtet. Die Provider-Zustaende selbst kommen ungecacht,
 * damit ein "Jetzt ausführen" sofort sichtbar wird.
 */
final class SourceMonitorService
{
    private const COUNT_CACHE_SECONDS = 60;

    public function __construct(
        private readonly SourceRegistry $registry,
        private readonly ContentTenantContext $context,
    ) {}

    /**
     * Eine Karte je Connector.
     *
     * @return list<array<string, mixed>>
     */
    public function connectors(): array
    {
        $states = ProviderState::query()->get()->keyBy('provider');
        $counts = $this->itemCounts();
        $tenants = $this->tenants();
        $cards = [];

        foreach ($this->registry->all() as $key => $connector) {
            /** @var ProviderState|null $state */
            $state = $states->get($key);
            $setting = $this->registry->settingFor($key);
            $enabled = $setting?->is_enabled ?? true;
            $declared = $this->registry->declaredFrequencyOf($connector);
            $effective = $this->registry->frequencyOf($connector);
            $weight = $setting?->weight ?? SourceSetting::DEFAULT_WEIGHT;

            $cards[] = [
                'key' => $key,
                'label' => $this->label($key),
                'frequency' => $effective->label(),
                'frequency_effective' => $effective->value,
                'frequency_declared' => $declared->value,
                'frequency_deviates' => $effective !== $declared,
                'weight' => $weight,
                'weight_deviates' => $weight !== SourceSetting::DEFAULT_WEIGHT,
                'is_enabled' => $enabled,
                'deviates' => $setting?->deviates() ?? false,
                'disabled_reason' => $enabled ? null : $setting?->disabled_reason,
                'disabled_by' => $enabled ? null : $this->userName($setting),
                'disabled_at' => $enabled ? null : $setting?->updated_at?->format('d.m.Y'),
                'credentials' => $this->credentialState($key),
                'status' => $state?->status ?? ProviderState::STATUS_OK,
                'status_label' => $enabled ? $this->statusLabel($state) : __('abgeschaltet'),
                'display_status' => $enabled ? $this->displayStatus($state) : DisplayStatus::ARCHIVED->value,
                'last_success' => $state?->last_success_at?->diffForHumans(),
                'last_success_at' => $state?->last_success_at?->format('d.m.Y H:i'),
                'last_failure_at' => $state?->last_failure_at?->format('d.m.Y H:i'),
                'consecutive_failures' => (int) ($state?->consecutive_failures ?? 0),
                'requests_today' => (int) ($state?->requests_today ?? 0),
                'cost_today_usd' => (float) ($state?->cost_today_usd ?? 0),
                'circuit_open_until' => $state?->circuit_open_until?->format('d.m.Y H:i'),
                'last_error' => $state?->last_error,
                'items' => (int) ($counts[$key] ?? 0),
                'runs' => $this->runs($state, $tenants),
                'never_ran' => $state === null,
            ];
        }

        // Gestoerte zuerst (§6), Abgeschaltete ganz ans Ende (§7a): sie sind
        // kein Problem, sondern eine Entscheidung.
        usort($cards, fn (array $a, array $b): int => [$this->severity($a), $a['label']] <=> [$this->severity($b), $b['label']]);

        return $cards;
    }

    /**
     * Zusammenfassungszeile ueber dem Raster.
     *
     * @param  list<array<string, mixed>>  $connectors
     * @return array{total: int, active: int, healthy: int, impaired: int, disabled: int, last_run: string|null}
     */
    public function summary(array $connectors): array
    {
        $active = array_values(array_filter($connectors, fn (array $card): bool => $card['is_enabled']));
        $disabled = count($connectors) - count($active);

        $healthy = count(array_filter(
            $active,
            fn (array $card): bool => $card['display_status'] === DisplayStatus::PUBLISHED->value,
        ));

        $lastRun = null;

        foreach ($connectors as $card) {
            foreach ($card['runs'] as $run) {
                if ($run['at'] !== null && ($lastRun === null || $run['at'] > $lastRun)) {
                    $lastRun = $run['at'];
                }
            }
        }

        return [
            'total' => count($connectors),
            'active' => count($active),
            'healthy' => $healthy,
            // Abgeschaltete zaehlen nicht als "braucht Aufmerksamkeit" (§7a).
            'impaired' => count($active) - $healthy,
            'disabled' => $disabled,
            'last_run' => $lastRun !== null ? Carbon::parse($lastRun)->format('d.m.Y H:i') : null,
        ];
    }

    /**
     * Manueller Abruf. Der Lauf selbst geschieht in der Queue — ein
     * Connector kann mehrere Sekunden brauchen, und die Seite soll nicht
     * darauf warten.
     */
    public function runNow(string $connectorKey, int $tenantId): string
    {
        if (! $this->registry->has($connectorKey)) {
            throw new RuntimeException(__('Diese Quelle ist nicht registriert.'));
        }

        // Ein manueller Abruf, der den Schalter aushebelt, macht den Schalter
        // wertlos (§7a).
        if (! $this->registry->isEnabled($connectorKey)) {
            throw new RuntimeException(__('":source" ist abgeschaltet und wird auch von Hand nicht abgerufen.', [
                'source' => $this->label($connectorKey),
            ]));
        }

        $tenant = $this->tenant($tenantId);

        if ($tenant === null) {
            throw new RuntimeException(__('Für dieses Portal fehlt die Berechtigung.'));
        }

        RunSourceConnectorJob::dispatch($tenantId, $connectorKey);

        return __('":source" läuft für :portal. Das Ergebnis erscheint hier, sobald der Lauf durch ist.', [
            'source' => $this->label($connectorKey),
            'portal' => $tenant->name,
        ]);
    }

    /**
     * Aktuelle Werte einer Quelle fuer das Formular "Einstellen" (#55),
     * design/content-dashboard.md, §7a.
     *
     * @return array<string, mixed>
     */
    public function configuration(string $connectorKey): array
    {
        if (! $this->registry->has($connectorKey)) {
            throw new RuntimeException(__('Diese Quelle ist nicht registriert.'));
        }

        $connector = $this->registry->get($connectorKey);
        $setting = $this->registry->settingFor($connectorKey);
        $declared = $this->registry->declaredFrequencyOf($connector);

        return [
            'key' => $connectorKey,
            'label' => $this->label($connectorKey),
            'is_enabled' => $setting?->is_enabled ?? true,
            'disabled_reason' => $setting?->disabled_reason,
            'frequency' => $this->registry->frequencyOf($connector)->value,
            'declared' => $declared->value,
            'weight' => $setting?->weight ?? SourceSetting::DEFAULT_WEIGHT,
            'deviates' => $setting?->deviates() ?? false,
            'credentials' => $this->credentialState($connectorKey),
        ];
    }

    /**
     * Auswahlliste des Rhythmus: die Deklaration und alle selteneren Werte.
     * Haeufiger als die Deklaration gibt es nicht — sie bildet Quellentakt,
     * Ratenlimit und Kosten ab.
     *
     * @return array<string, string>
     */
    public function frequencyOptions(string $connectorKey): array
    {
        $declared = $this->registry->declaredFrequencyOf($this->registry->get($connectorKey));
        $options = [];

        foreach (SourceFrequency::fromDeclared($declared) as $frequency) {
            $options[$frequency->value] = $frequency === $declared
                ? __(':label (Vorgabe)', ['label' => $frequency->label()])
                : $frequency->label();
        }

        return $options;
    }

    /**
     * Speichert die Konfiguration und liefert den Satz fuer die Rueckmeldung:
     * die Folge, nicht den Vorgang.
     *
     * Ein Datensatz, der nur Vorgabewerte traegt, wird geloescht statt
     * geschrieben — sonst zeigte die Karte eine Abweichung, die keine ist.
     *
     * @param  array<string, mixed>  $data
     */
    public function saveConfiguration(string $connectorKey, array $data): string
    {
        if (! $this->registry->has($connectorKey)) {
            throw new RuntimeException(__('Diese Quelle ist nicht registriert.'));
        }

        $connector = $this->registry->get($connectorKey);
        $declared = $this->registry->declaredFrequencyOf($connector);
        $enabled = (bool) ($data['is_enabled'] ?? true);
        $reason = trim((string) ($data['disabled_reason'] ?? ''));
        $weight = (int) ($data['weight'] ?? SourceSetting::DEFAULT_WEIGHT);

        if (! $enabled && $reason === '') {
            throw new RuntimeException(__('Bitte einen Grund für das Abschalten angeben.'));
        }

        if ($weight < 0 || $weight > SourceSetting::MAX_WEIGHT) {
            throw new RuntimeException(__('Das Gewicht muss zwischen 0 und 200 Prozent liegen.'));
        }

        $frequency = SourceFrequency::tryFrom((string) ($data['frequency'] ?? $declared->value)) ?? $declared;

        // Zweite Verteidigungslinie zur Auswahlliste: ein haeufigerer Wert
        // kann nur ueber eine manipulierte Anfrage kommen und wird abgewiesen.
        if (! $frequency->isAtLeastAsRareAs($declared)) {
            throw new RuntimeException(__('Häufiger als die Vorgabe ":label" ist nicht möglich.', [
                'label' => $declared->label(),
            ]));
        }

        $override = $frequency === $declared ? null : $frequency->value;
        $isDefault = $enabled && $override === null && $weight === SourceSetting::DEFAULT_WEIGHT;

        if ($isDefault) {
            SourceSetting::query()->where('source_key', $connectorKey)->delete();
        } else {
            SourceSetting::query()->updateOrCreate(
                ['source_key' => $connectorKey],
                [
                    'is_enabled' => $enabled,
                    'frequency_override' => $override,
                    'weight' => $weight,
                    // Beim Wiedereinschalten verliert der Grund seinen Bezug.
                    'disabled_reason' => $enabled ? null : Str::limit($reason, 200, ''),
                    'updated_by_user_id' => $this->currentUserId(),
                ],
            );
        }

        $this->registry->flushSettings();

        if (! $enabled) {
            return __('":source" ist abgeschaltet.', ['source' => $this->label($connectorKey)]);
        }

        return __('":source" läuft ab jetzt :frequency, Gewicht :weight %.', [
            'source' => $this->label($connectorKey),
            'frequency' => $frequency->label(),
            'weight' => $weight,
        ]);
    }

    /**
     * Zurueck auf die Vorgabe: der Datensatz verschwindet, es bleibt kein
     * stiller Rest.
     */
    public function resetConfiguration(string $connectorKey): string
    {
        SourceSetting::query()->where('source_key', $connectorKey)->delete();
        $this->registry->flushSettings();

        return __('":source" läuft wieder nach Vorgabe.', ['source' => $this->label($connectorKey)]);
    }

    /**
     * Connectoren aus dem Katalog, die in der Serverkonfiguration aus sind
     * und deshalb gar nicht registriert werden. Sie gehoeren in den Block
     * "Nicht eingerichtet" unter dem Raster (§7a).
     *
     * @return list<array{key: string, label: string, env: string, credentials: array<string, mixed>}>
     */
    public function notConfigured(): array
    {
        $rows = [];

        foreach ((array) config('content.sources.catalog', []) as $key => $entry) {
            if ($this->registry->has((string) $key)) {
                continue;
            }

            $rows[] = [
                'key' => (string) $key,
                'label' => $this->label((string) $key),
                'env' => (string) ($entry['env'] ?? ''),
                'credentials' => $this->credentialState((string) $key),
            ];
        }

        usort($rows, fn (array $a, array $b): int => $a['label'] <=> $b['label']);

        return $rows;
    }

    /**
     * Zustandssatz zum Zugang — kein Eingabefeld, nirgends (§7a).
     *
     * Geprueft wird ausschliesslich, ob die Werte hinterlegt sind, nicht ob
     * sie tragen. "Hinterlegt" ist das, was wir belegen koennen; "gültig"
     * waere eine Behauptung, die der erste 401 widerlegt.
     *
     * @return array{state: 'none'|'present'|'missing', text: string, missing: list<string>}
     */
    public function credentialState(string $connectorKey): array
    {
        /** @var array<string, string> $required */
        $required = (array) config("content.sources.catalog.{$connectorKey}.credentials", []);

        if ($required === []) {
            return [
                'state' => 'none',
                'text' => __('Kein Zugang nötig — die Quelle ist frei abrufbar.'),
                'missing' => [],
            ];
        }

        $missing = [];

        foreach ($required as $envName => $configKey) {
            if ((string) config((string) $configKey, '') === '') {
                $missing[] = (string) $envName;
            }
        }

        if ($missing === []) {
            return [
                'state' => 'present',
                'text' => __('Zugang aus der Serverkonfiguration hinterlegt.'),
                'missing' => [],
            ];
        }

        return [
            'state' => 'missing',
            'text' => __('Zugang fehlt: :keys.', ['keys' => implode(', ', $missing)]),
            'missing' => $missing,
        ];
    }

    /**
     * Alle sichtbaren Portale — der Abruf braucht immer ein Portal, weil
     * source_items in der Mandantendatenbank landen.
     *
     * @return list<Tenant>
     */
    public function tenants(): array
    {
        /** @var list<Tenant> $tenants */
        $tenants = $this->context->available()->values()->all();

        return $tenants;
    }

    /**
     * Letzte Laeufe je Portal aus provider_states.meta_json (#7).
     *
     * @param  list<Tenant>  $tenants
     * @return list<array{tenant_id: int, tenant: string, at: string|null, ago: string|null}>
     */
    private function runs(?ProviderState $state, array $tenants): array
    {
        $meta = is_array($state?->meta_json) ? $state->meta_json : [];
        $lastRuns = (array) ($meta['last_run_at'] ?? []);
        $runs = [];

        foreach ($tenants as $tenant) {
            $at = $lastRuns[(string) $tenant->getKey()] ?? null;

            $runs[] = [
                'tenant_id' => (int) $tenant->getKey(),
                'tenant' => (string) $tenant->name,
                'at' => is_string($at) ? $at : null,
                'ago' => is_string($at) ? Carbon::parse($at)->diffForHumans() : null,
            ];
        }

        usort($runs, fn (array $a, array $b): int => [$a['at'] ?? '', $a['tenant']] <=> [$b['at'] ?? '', $b['tenant']]);

        return $runs;
    }

    /**
     * Signale je Connector ueber alle sichtbaren Portale.
     *
     * @return array<string, int>
     */
    private function itemCounts(): array
    {
        $ids = array_map(fn (Tenant $tenant): int => (int) $tenant->getKey(), $this->tenants());
        sort($ids);

        return Cache::remember(
            'content.source-monitor.counts.'.md5(implode(',', $ids)),
            self::COUNT_CACHE_SECONDS,
            function () use ($ids): array {
                $counts = [];

                foreach ($this->tenants() as $tenant) {
                    if (! in_array((int) $tenant->getKey(), $ids, true)) {
                        continue;
                    }

                    try {
                        $rows = $tenant->run(fn (): array => SourceItem::query()
                            ->selectRaw('source_key, count(*) as aggregate')
                            ->groupBy('source_key')
                            ->pluck('aggregate', 'source_key')
                            ->all());
                    } catch (Throwable $exception) {
                        Log::warning('Quellen-Monitor: Portal uebersprungen.', [
                            'tenant_id' => $tenant->getKey(),
                            'exception' => $exception->getMessage(),
                        ]);

                        continue;
                    }

                    foreach ($rows as $key => $count) {
                        $counts[(string) $key] = ($counts[(string) $key] ?? 0) + (int) $count;
                    }
                }

                return $counts;
            },
        );
    }

    /**
     * Wer die Quelle abgeschaltet hat. Ohne Namen bleibt die Karte bei der
     * Sache und behauptet keinen Urheber.
     */
    private function userName(?SourceSetting $setting): ?string
    {
        if ($setting?->updated_by_user_id === null) {
            return null;
        }

        return User::query()->find($setting->updated_by_user_id)?->name;
    }

    private function currentUserId(): ?int
    {
        try {
            $user = Filament::auth()->user();
        } catch (Throwable) {
            // Ausserhalb des Panels (Konsole, Queue) gibt es keinen Nutzer.
            return null;
        }

        return $user instanceof User ? (int) $user->getKey() : null;
    }

    private function tenant(int $id): ?Tenant
    {
        foreach ($this->tenants() as $tenant) {
            if ((int) $tenant->getKey() === $id) {
                return $tenant;
            }
        }

        return null;
    }

    /**
     * Klartextname eines Connectors. Bekannte Schluessel bekommen den Namen,
     * unter dem die Redaktion die Quelle kennt; alles andere wird lesbar
     * gemacht, statt den technischen Schluessel zu zeigen.
     */
    public function label(string $key): string
    {
        return match ($key) {
            'google_trends_rss' => __('Google Trends (RSS)'),
            'dataforseo_trends' => __('Google Trends (DataForSEO)'),
            'gsc_gap' => __('Search Console — Content-Lücken'),
            'google_news_rss' => __('Regionale News'),
            'rss_feeds' => __('Förderung und Recht (RSS)'),
            'foerderdatenbank' => __('Förderdatenbank'),
            'statistics' => __('Statistik'),
            'seasonal_calendar' => __('Saisonkalender'),
            'portal_data' => __('Portal-Eigendaten'),
            default => (string) config("content.sources.catalog.{$key}.label", Str::headline($key)),
        };
    }

    /**
     * Ein Connector hat keinen DisplayStatus. Die Statusfarben werden hier
     * mit eigenen Beschriftungen weiterverwendet — aber keine achte Farbe
     * eingefuehrt (design/content-dashboard.md, §6).
     */
    private function displayStatus(?ProviderState $state): string
    {
        return match ($state?->status) {
            null, ProviderState::STATUS_OK => DisplayStatus::PUBLISHED->value,
            ProviderState::STATUS_DEGRADED, ProviderState::STATUS_PAUSED => DisplayStatus::REVIEW->value,
            ProviderState::STATUS_FAILED => DisplayStatus::FAILED->value,
            ProviderState::STATUS_DISABLED => DisplayStatus::ARCHIVED->value,
            default => DisplayStatus::FAILED->value,
        };
    }

    private function statusLabel(?ProviderState $state): string
    {
        return match ($state?->status) {
            null => __('noch nicht gelaufen'),
            ProviderState::STATUS_OK => __('aktuell'),
            ProviderState::STATUS_DEGRADED => __('verzögert'),
            ProviderState::STATUS_PAUSED => __('angehalten (Budget)'),
            ProviderState::STATUS_FAILED => __('nicht verfügbar'),
            ProviderState::STATUS_DISABLED => __('abgeschaltet'),
            default => __('ausgefallen'),
        };
    }

    /**
     * Gestoerte Quellen zuerst — das Raster soll von selbst erzaehlen, wo es
     * klemmt. Abgeschaltete stehen hinter allem anderen.
     *
     * @param  array<string, mixed>  $card
     */
    private function severity(array $card): int
    {
        if (! $card['is_enabled']) {
            return 4;
        }

        return match ($card['display_status']) {
            DisplayStatus::FAILED->value => 0,
            DisplayStatus::REVIEW->value => 1,
            DisplayStatus::ARCHIVED->value => 2,
            default => 3,
        };
    }
}
