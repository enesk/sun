<?php

declare(strict_types=1);

namespace App\Content\Services;

use App\Content\Enums\DisplayStatus;
use App\Content\Models\ArticleDraft;
use App\Content\Models\Central\ContentAlert;
use App\Content\Models\Central\LlmUsageLog;
use App\Content\Models\Central\ProviderState;
use App\Content\Models\TenantContentSetting;
use App\Content\Models\TopicCandidate;
use App\Content\Support\BranchResolver;
use App\Models\Tenant;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Netzwerkweite Aggregation fuer die Uebersicht des Content-Panels (#19).
 *
 * Die Zahlen liegen in 24 getrennten Tenant-Datenbanken; es gibt keine
 * zentrale Artikeltabelle, ueber die sich das in einer Abfrage beantworten
 * liesse. Deshalb laeuft je Portal ein kurzer tenancy()-Durchgang mit genau
 * drei Abfragen (Einstellungen, Entwuerfe des Tages, ausgewaehlte Themen)
 * und das Gesamtergebnis liegt 60 Sekunden im Cache — ohne Cache waere die
 * Uebersicht bei jedem Aufruf 70+ Abfragen schwer und die Sekundengrenze
 * aus der Abnahme nicht zu halten.
 *
 * Kosten und Provider-Zustand kommen aus den Central-Tabellen und brauchen
 * keinen Tenant-Kontext.
 */
final class ContentOverviewService
{
    public const CACHE_SECONDS = 60;

    private const CACHE_PREFIX = 'content:overview:';

    /**
     * Ein Portal gilt als gestoert, wenn bis hierhin nichts erzeugt wurde.
     */
    private const EVENT_LIMIT = 8;

    public function __construct(private readonly ContentTenantContext $context) {}

    /**
     * Vollstaendige Momentaufnahme fuer die Uebersicht.
     *
     * @return array<string, mixed>
     */
    public function snapshot(bool $refresh = false): array
    {
        $tenants = $this->tenants();
        $key = $this->cacheKey($tenants);

        if ($refresh) {
            Cache::forget($key);
        }

        return Cache::remember($key, self::CACHE_SECONDS, fn (): array => $this->build($tenants));
    }

    /**
     * Zahl fuer die Zaehlmarke der Pruefung in der Navigation.
     */
    public function reviewCount(): int
    {
        return (int) ($this->snapshot()['counts'][DisplayStatus::REVIEW->value] ?? 0);
    }

    /**
     * @param  \Illuminate\Support\Collection<int, Tenant>  $tenants
     * @return array<string, mixed>
     */
    private function build($tenants): array
    {
        $today = CarbonImmutable::today();
        $portals = [];
        $counts = array_fill_keys(array_map(fn (DisplayStatus $s) => $s->value, DisplayStatus::cases()), 0);
        $events = [];
        $target = 0;
        $published = 0;
        $unavailable = [];

        foreach ($tenants as $tenant) {
            $portal = $this->portalSnapshot($tenant, $today);

            if ($portal === null) {
                $unavailable[] = (string) $tenant->name;

                continue;
            }

            foreach ($portal['counts'] as $status => $count) {
                $counts[$status] += $count;
            }

            $target += $portal['target'];
            $published += $portal['published'];
            $events = array_merge($events, $portal['events']);
            unset($portal['events']);

            $portals[] = $portal;
        }

        usort($portals, function (array $a, array $b): int {
            return [$b['has_problem'], $a['name']] <=> [$a['has_problem'], $b['name']];
        });

        usort($events, fn (array $a, array $b): int => $b['at'] <=> $a['at']);

        $cost = $this->cost($today);
        $sources = $this->sources();

        return [
            'generated_at' => CarbonImmutable::now(),
            'target' => [
                'total' => $target,
                'published' => $published,
                'expected_now' => $this->expectedByNow($target),
            ],
            'counts' => $counts,
            'portals' => $portals,
            'unavailable' => $unavailable,
            'cost' => $cost,
            'sources' => $sources,
            'events' => array_slice($events, 0, self::EVENT_LIMIT),
            'alarms' => $this->alarms($portals, $unavailable, $cost, $sources, $target, $published),
        ];
    }

    /**
     * Kennzahlen eines Portals. Rueckgabe null, wenn die Tenant-Datenbank die
     * Pipeline-Tabellen (noch) nicht hat — das ist auf Staging der Normalfall
     * und darf die Uebersicht nicht abbrechen.
     *
     * @return array<string, mixed>|null
     */
    private function portalSnapshot(Tenant $tenant, CarbonImmutable $today): ?array
    {
        try {
            return $tenant->run(function () use ($tenant, $today): array {
                $settings = TenantContentSetting::query()->first();
                $target = (int) ($settings?->articles_per_day
                    ?? config('content.targets.articles_per_tenant_per_day', 2));

                // Aktualisierungen (#24) bleiben aussen vor: sie haengen an
                // keinem Slot des Tages und wuerden das Tagesziel von zwei
                // neuen Artikeln sonst rechnerisch erfuellen, ohne dass ein
                // neuer Artikel entstanden ist.
                $drafts = ArticleDraft::query()
                    ->originals()
                    ->select(['id', 'title', 'status', 'withdrawn_at', 'scheduled_for', 'published_at', 'updated_at'])
                    ->where(function ($query) use ($today): void {
                        $query
                            ->whereBetween('scheduled_for', [$today->startOfDay(), $today->endOfDay()])
                            ->orWhereBetween('published_at', [$today->startOfDay(), $today->endOfDay()])
                            ->orWhereBetween('created_at', [$today->startOfDay(), $today->endOfDay()]);
                    })
                    ->orderByRaw('COALESCE(published_at, scheduled_for, created_at)')
                    ->limit(50)
                    ->get();

                $openTopics = TopicCandidate::query()->selectedFor($today)->count();

                // Entwuerfe, an denen das Qualitaetsgate (#15) aufgegeben hat,
                // ohne dass noch ein Versuch fuer den Tages-Slot offen war.
                $atRisk = ArticleDraft::query()
                    ->originals()
                    ->whereBetween('created_at', [$today->startOfDay(), $today->endOfDay()])
                    ->where('quality_report_json->quality->at_risk', true)
                    ->count();

                return $this->fromDrafts($tenant, $target, $drafts, $openTopics, $atRisk);
            });
        } catch (Throwable $exception) {
            Log::warning('Content-Uebersicht: Portal uebersprungen.', [
                'tenant' => $tenant->getKey(),
                'message' => $exception->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * @param  \Illuminate\Database\Eloquent\Collection<int, ArticleDraft>  $drafts
     * @return array<string, mixed>
     */
    private function fromDrafts(Tenant $tenant, int $target, $drafts, int $openTopics, int $atRisk = 0): array
    {
        $counts = array_fill_keys(array_map(fn (DisplayStatus $s) => $s->value, DisplayStatus::cases()), 0);
        $dots = [];
        $events = [];

        foreach ($drafts as $draft) {
            $status = $draft->display_status;
            $counts[$status->value]++;

            if (count($dots) < $target) {
                $dots[] = $status->value;
            }

            if (in_array($status, [DisplayStatus::PUBLISHED, DisplayStatus::FAILED], true)) {
                $events[] = [
                    'at' => ($draft->published_at ?? $draft->updated_at)?->getTimestamp() ?? 0,
                    'time' => ($draft->published_at ?? $draft->updated_at)?->format('H:i') ?? '',
                    'portal' => (string) $tenant->name,
                    'status' => $status->value,
                    'message' => $status === DisplayStatus::PUBLISHED
                        ? __('„:title" ist erschienen.', ['title' => $draft->title])
                        : __('„:title" ist gescheitert.', ['title' => $draft->title]),
                ];
            }
        }

        // Offene Plaetze: erst die eingeplanten Themen ohne Entwurf, dann leer.
        for ($i = count($dots); $i < $target; $i++) {
            $dots[] = $openTopics-- > 0 ? DisplayStatus::SCHEDULED->value : null;
        }

        $published = $counts[DisplayStatus::PUBLISHED->value];
        $failed = $counts[DisplayStatus::FAILED->value];

        return [
            'id' => (int) $tenant->getKey(),
            'name' => (string) $tenant->name,
            'branch' => BranchResolver::label(BranchResolver::resolve($tenant) ?? '') ?? __('Ohne Branche'),
            'target' => $target,
            'published' => $published,
            'failed' => $failed,
            'counts' => $counts,
            'dots' => $dots,
            'at_risk' => $atRisk,
            'has_problem' => $atRisk > 0
                || $failed > 0
                || ($drafts->isEmpty() && $openTopics === 0 && $this->dayHasStarted())
                || ($published < $target && $this->dayIsOver()),
            'events' => $events,
        ];
    }

    /**
     * @return array<string, float>
     */
    private function cost(CarbonImmutable $today): array
    {
        $daily = (float) config('content.budget.daily_usd', 35.0);
        $monthly = (float) config('content.budget.monthly_usd', 1050.0);

        $spentToday = (float) LlmUsageLog::query()
            ->whereBetween('created_at', [$today->startOfDay(), $today->endOfDay()])
            ->sum('cost_usd');

        $spentMonth = (float) LlmUsageLog::query()
            ->whereBetween('created_at', [$today->startOfMonth(), $today->endOfMonth()])
            ->sum('cost_usd');

        return [
            'today' => $spentToday,
            'daily_budget' => $daily,
            'today_share' => $daily > 0 ? $spentToday / $daily : 0.0,
            'month' => $spentMonth,
            'monthly_budget' => $monthly,
            'month_share' => $monthly > 0 ? $spentMonth / $monthly : 0.0,
        ];
    }

    /**
     * Quellenlage je Provider. Ein Provider ohne Zeile gilt als noch nie
     * gelaufen und wird nicht als Ausfall gemeldet.
     *
     * @return array<int, array<string, mixed>>
     */
    private function sources(): array
    {
        return ProviderState::query()
            ->orderBy('provider')
            ->get()
            ->map(fn (ProviderState $state): array => [
                'provider' => $state->provider,
                'status' => $state->status,
                'label' => match ($state->status) {
                    ProviderState::STATUS_OK => __('aktuell'),
                    ProviderState::STATUS_DEGRADED => __('verzögert'),
                    ProviderState::STATUS_PAUSED => __('angehalten (Budget)'),
                    ProviderState::STATUS_FAILED => __('nicht verfügbar'),
                    ProviderState::STATUS_DISABLED => __('abgeschaltet'),
                    default => __('ausgefallen'),
                },
                'display_status' => match ($state->status) {
                    ProviderState::STATUS_OK => DisplayStatus::PUBLISHED->value,
                    ProviderState::STATUS_DEGRADED, ProviderState::STATUS_PAUSED => DisplayStatus::REVIEW->value,
                    ProviderState::STATUS_FAILED => DisplayStatus::FAILED->value,
                    ProviderState::STATUS_DISABLED => DisplayStatus::ARCHIVED->value,
                    default => DisplayStatus::FAILED->value,
                },
                'since' => $state->last_failure_at?->format('d.m. H:i'),
                'as_of' => $state->last_success_at?->format('d.m. H:i'),
            ])
            ->all();
    }

    /**
     * Hoechstens ein Band je Ursache, in der Rangfolge aus
     * design/content-dashboard.md §1a: erst alle `failed`-Baender, dann die
     * `review`-Baender. Ein Hinweis darf kein Verlust aus den ersten drei
     * Plaetzen des Stapels draengen.
     *
     * @param  array<int, array<string, mixed>>  $portals
     * @param  array<int, string>  $unavailable
     * @param  array<string, float>  $cost
     * @param  array<int, array<string, mixed>>  $sources
     * @return array<int, array<string, string>>
     */
    private function alarms(array $portals, array $unavailable, array $cost, array $sources, int $target, int $published): array
    {
        // Zuerst die festgehaltenen Alarme des Orchestrators (#22): ein
        // verlorener Slot ist die dringendste Meldung, die es gibt, und er
        // steht bereits mit Portalnamen und Grund in der Tabelle.
        $alarms = $this->persistedAlarms();

        if ($cost['today_share'] >= 1.0) {
            $alarms[] = [
                'level' => DisplayStatus::FAILED->value,
                'message' => __('Das Tagesbudget ist erschöpft. Die Produktion ist angehalten.'),
            ];
        }

        $atRisk = array_values(array_filter($portals, fn (array $portal): bool => ($portal['at_risk'] ?? 0) > 0));

        if ($atRisk !== []) {
            $alarms[] = [
                'level' => DisplayStatus::FAILED->value,
                'message' => trans_choice(
                    '{1}Tagesziel gefährdet: für ein Portal ist ein Artikel durch die Qualitätsprüfung gefallen, ohne dass ein Versuch offen blieb (:names).|[2,*]Tagesziel gefährdet: für :count Portale ist ein Artikel durch die Qualitätsprüfung gefallen, ohne dass ein Versuch offen blieb (:names).',
                    count($atRisk),
                    ['count' => count($atRisk), 'names' => implode(', ', array_column($atRisk, 'name'))],
                ),
            ];
        }

        // Ein toter Zugang (#104) steht schon als eigener Alarm in Klartext
        // im Stapel; er darf nicht zusaetzlich als „Quelle ausgefallen"
        // einen der drei Plaetze belegen.
        $broken = array_values(array_filter(
            $sources,
            fn (array $source): bool => $source['display_status'] === DisplayStatus::FAILED->value
                && $source['status'] !== ProviderState::STATUS_FAILED,
        ));

        if ($broken !== []) {
            $alarms[] = [
                'level' => DisplayStatus::FAILED->value,
                'message' => trans_choice(
                    '{1}Eine Quelle ist ausgefallen (:names).|[2,*]:count Quellen sind ausgefallen (:names).',
                    count($broken),
                    ['count' => count($broken), 'names' => implode(', ', array_column($broken, 'provider'))],
                ),
            ];
        }

        $idle = array_values(array_filter($portals, fn (array $portal): bool => $portal['has_problem']));

        if ($idle !== []) {
            $alarms[] = [
                'level' => DisplayStatus::FAILED->value,
                'message' => trans_choice(
                    '{1}Für ein Portal läuft heute nichts nach Plan.|[2,*]Für :count Portale läuft heute nichts nach Plan.',
                    count($idle),
                    ['count' => count($idle)],
                ),
            ];
        }

        if ($cost['today_share'] < 1.0 && $cost['today_share'] >= (float) config('content.budget.warn_threshold', 0.8)) {
            $alarms[] = [
                'level' => DisplayStatus::REVIEW->value,
                'message' => __(':percent % des Tagesbudgets sind verbraucht.', [
                    'percent' => (int) round($cost['today_share'] * 100),
                ]),
            ];
        }

        $expected = $this->expectedByNow($target);

        if ($published < $expected) {
            $alarms[] = [
                'level' => DisplayStatus::REVIEW->value,
                'message' => __('Rückstand von :count Artikeln gegenüber dem Sollwert dieser Uhrzeit.', [
                    'count' => $expected - $published,
                ]),
            ];
        }

        if ($unavailable !== []) {
            $alarms[] = [
                'level' => DisplayStatus::REVIEW->value,
                'message' => __('Für :count Portale sind die Pipeline-Tabellen nicht erreichbar.', [
                    'count' => count($unavailable),
                ]),
            ];
        }

        return $alarms;
    }

    /**
     * Die offenen Alarme aus `content_alerts` (#22), Kritisches zuerst.
     *
     * @return array<int, array<string, string>>
     */
    private function persistedAlarms(): array
    {
        try {
            return ContentAlert::query()
                ->open()
                ->orderByRaw("CASE level WHEN 'critical' THEN 0 WHEN 'warning' THEN 1 ELSE 2 END")
                ->orderByDesc('last_seen_at')
                ->limit(self::EVENT_LIMIT)
                ->get()
                ->map(static fn (ContentAlert $alert): array => [
                    'level' => $alert->displayStatus(),
                    'message' => (string) $alert->message,
                ])
                ->all();
        } catch (Throwable $exception) {
            // Umgebung ohne die Alarmtabelle: die Uebersicht bleibt
            // benutzbar und meldet weiter, was sie selbst errechnet.
            Log::warning('Content-Uebersicht: Alarme nicht lesbar.', [
                'message' => $exception->getMessage(),
            ]);

            return [];
        }
    }

    /**
     * Sollwert zur aktuellen Uhrzeit. Vor dem Veroeffentlichungsfenster ist
     * ein Rueckstand normal und wird nicht als Stoerung gelesen.
     */
    public function expectedByNow(int $target): int
    {
        [$start, $end] = $this->publishWindow();
        $now = CarbonImmutable::now();

        if ($now->lessThanOrEqualTo($start)) {
            return 0;
        }

        if ($now->greaterThanOrEqualTo($end)) {
            return $target;
        }

        $share = $start->diffInSeconds($now) / max(1, $start->diffInSeconds($end));

        return (int) floor($target * $share);
    }

    private function dayHasStarted(): bool
    {
        return CarbonImmutable::now()->greaterThan($this->publishWindow()[0]);
    }

    private function dayIsOver(): bool
    {
        return CarbonImmutable::now()->greaterThan($this->publishWindow()[1]);
    }

    /**
     * @return array{0: CarbonImmutable, 1: CarbonImmutable}
     */
    private function publishWindow(): array
    {
        $window = (array) config('content.pipeline.schedule.publish_window', ['09:00', '17:00']);
        $today = CarbonImmutable::today();

        return [
            $today->setTimeFromTimeString((string) ($window[0] ?? '09:00')),
            $today->setTimeFromTimeString((string) ($window[1] ?? '17:00')),
        ];
    }

    /**
     * Portale, die der angemeldete Account sehen darf — bei gewaehltem Portal
     * nur dieses eine.
     *
     * @return \Illuminate\Support\Collection<int, Tenant>
     */
    private function tenants()
    {
        /** @var \Illuminate\Support\Collection<int, Tenant> $available */
        $available = collect($this->context->available()->all());
        $selected = $this->context->selectedId();

        if ($selected === null) {
            return $available;
        }

        return $available->filter(fn (Tenant $tenant): bool => (int) $tenant->getKey() === $selected)->values();
    }

    /**
     * @param  \Illuminate\Support\Collection<int, Tenant>  $tenants
     */
    private function cacheKey($tenants): string
    {
        $ids = $tenants->map(fn (Tenant $tenant) => (int) $tenant->getKey())->sort()->implode(',');

        return self::CACHE_PREFIX.CarbonImmutable::today()->toDateString().':'.md5($ids);
    }
}
