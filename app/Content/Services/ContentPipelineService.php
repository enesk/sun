<?php

declare(strict_types=1);

namespace App\Content\Services;

use App\Content\Enums\DisplayStatus;
use App\Content\Enums\DraftStatus;
use App\Content\Enums\TopicStatus;
use App\Content\Llm\BudgetGuard;
use App\Content\Llm\Exceptions\BudgetExceededException;
use App\Content\Models\ArticleDraft;
use App\Content\Models\DraftSource;
use App\Content\Models\SourceItem;
use App\Content\Models\TenantContentSetting;
use App\Content\Models\TopicCandidate;
use App\Content\Support\BranchResolver;
use App\Content\Support\GenerationStart;
use App\Content\Support\PipelineCardKey;
use App\Models\Tenant;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

/**
 * Karten, Kalendertage und Handlungen der Produktionsansicht (#19).
 *
 * Board und Kalender zeigen dieselben Objekte in unterschiedlicher Anordnung
 * und ziehen sie aus 24 Tenant-Datenbanken zusammen. Diese Klasse haelt den
 * tenancy()-Durchgang und die Abbildung auf DisplayStatus an einer Stelle;
 * die Livewire-Komponenten kennen keine Modelle.
 *
 * Anders als die Uebersicht wird hier NICHT zwischengespeichert: das Board
 * pollt alle 15 Sekunden und soll Statuswechsel sofort zeigen.
 */
final class ContentPipelineService
{
    /**
     * Karten je Spalte. Mehr zeigt das Board nicht — der Rest steht in der
     * Artikelliste (design/content-dashboard.md, §2).
     */
    public const COLUMN_LIMIT = 50;

    /**
     * Zeilen je Seite der Artikelliste. Feste Groesse, keine waehlbare
     * Seitengroesse (design/content-dashboard.md, §3a).
     */
    public const PAGE_SIZE = 50;

    /**
     * Erlaubte Sortierschluessel der Artikelliste. Region ist bewusst nicht
     * sortierbar — dafuer ist der Regionsfilter da.
     */
    public const SORTS = ['titel', 'portal', 'status', 'score', 'termin'];

    public const SORT_DEFAULT = 'termin';

    public const DIRECTION_ASC = 'auf';

    public const DIRECTION_DESC = 'ab';

    /**
     * Portale, die im letzten cards()-Durchgang nicht gelesen werden konnten.
     * Traegt das Band "Teilweise fehlgeschlagen" der Artikelliste.
     */
    private int $unreadableTenants = 0;

    /**
     * Sperrfrist des Generators (GenerateDraftJob::uniqueFor()). Solange sie
     * laeuft, verwirft Laravel ein zweites Einreihen desselben Ziels — die
     * Oberflaeche sagt das deshalb selbst, statt einen Start zu melden, der
     * nie stattfindet.
     */
    private const GENERATION_LOCK_SECONDS = 7200;

    /**
     * Budgetstand des laufenden Requests. Eine Abfrage je Seitenaufbau, nicht
     * je Karte (#42, §4).
     *
     * @var array{available: bool, tight: bool, reason: ?string, limit_usd: float}|null
     */
    private ?array $generationBudget = null;

    /**
     * Obergrenze je Portal und Abfrage, damit ein einzelnes Portal das Board
     * nicht sprengt.
     */
    private const PER_TENANT_LIMIT = 200;

    public function __construct(
        private readonly ContentTenantContext $context,
        private readonly RefreshStatus $refresh,
    ) {}

    /**
     * Board-Spalten in der Reihenfolge aus DisplayStatus::board().
     *
     * @param  array<string, mixed>  $filters
     * @return array<int, array<string, mixed>>
     */
    public function board(array $filters = []): array
    {
        $cards = $this->cards($filters);
        $columns = [];

        foreach (DisplayStatus::board() as $status) {
            $matching = array_values(array_filter(
                $cards,
                fn (array $card): bool => $card['status'] === $status->value,
            ));

            usort($matching, fn (array $a, array $b): int => [$b['score'] ?? -1, $a['title']] <=> [$a['score'] ?? -1, $b['title']]);

            $columns[] = [
                'status' => $status->value,
                'label' => $status->label(),
                'total' => count($matching),
                'cards' => array_slice($matching, 0, self::COLUMN_LIMIT),
                'overflow' => max(0, count($matching) - self::COLUMN_LIMIT),
            ];
        }

        return $columns;
    }

    /**
     * Alle Karten ueber alle sichtbaren Portale.
     *
     * @param  array<string, mixed>  $filters
     * @return array<int, array<string, mixed>>
     */
    public function cards(array $filters = []): array
    {
        $cards = [];
        $this->unreadableTenants = 0;

        foreach ($this->tenants($filters) as $tenant) {
            foreach ($this->tenantCards($tenant, $filters) as $card) {
                $cards[] = $card;
            }
        }

        return $cards;
    }

    /**
     * Eine Seite der Artikelliste (design/content-dashboard.md, §3a).
     *
     * Sortiert wird ueber die zusammengefuehrten Karten aller Portale, nicht
     * je Portal: ein Score von 82 aus Portal B gehoert zwischen 85 und 79 aus
     * Portal A. Unbekannte Sortier- und Richtungswerte fallen still auf die
     * Vorgabe zurueck, damit eine geteilte URL nie in einen Fehler laeuft.
     *
     * @param  array<string, mixed>  $filters
     * @return array{rows: array<int, array<string, mixed>>, total: int, page: int, pages: int, from: int, to: int, sort: string, direction: string, portals: int, unreadable: int}
     */
    public function list(
        array $filters = [],
        string $sort = self::SORT_DEFAULT,
        string $direction = self::DIRECTION_ASC,
        int $page = 1,
    ): array {
        $sort = in_array($sort, self::SORTS, true) ? $sort : self::SORT_DEFAULT;
        $direction = $direction === self::DIRECTION_DESC ? self::DIRECTION_DESC : self::DIRECTION_ASC;
        $portals = $this->tenants($filters)->count();

        $cards = $this->cards($filters);
        usort($cards, $this->comparator($sort, $direction));

        $total = count($cards);
        $pages = max(1, (int) ceil($total / self::PAGE_SIZE));
        $page = max(1, min($page, $pages));
        $offset = ($page - 1) * self::PAGE_SIZE;

        return [
            'rows' => array_slice($cards, $offset, self::PAGE_SIZE),
            'total' => $total,
            'page' => $page,
            'pages' => $pages,
            'from' => $total === 0 ? 0 : $offset + 1,
            'to' => min($offset + self::PAGE_SIZE, $total),
            'sort' => $sort,
            'direction' => $direction,
            'portals' => $portals,
            'unreadable' => $this->unreadableTenants,
        ];
    }

    /**
     * Erstrichtung einer Spalte: Score und Termin absteigend, Text aufsteigend.
     */
    public static function initialDirection(string $sort): string
    {
        return in_array($sort, ['score', 'termin'], true) ? self::DIRECTION_DESC : self::DIRECTION_ASC;
    }

    /**
     * Vergleich zweier Karten fuer die Artikelliste.
     *
     * Nullwerte — kein Termin, kein Score — stehen in beiden Richtungen am
     * Ende: ein fehlender Wert ist keine kleine Zahl, sondern eine offene
     * Frage. Zweitschluessel ist immer Score absteigend, dann Titel
     * aufsteigend, damit die Reihenfolge ueber Seitengrenzen stabil bleibt.
     *
     * @return callable(array<string, mixed>, array<string, mixed>): int
     */
    private function comparator(string $sort, string $direction): callable
    {
        $factor = $direction === self::DIRECTION_DESC ? -1 : 1;

        return function (array $a, array $b) use ($sort, $factor): int {
            $result = match ($sort) {
                'titel' => $factor * $this->compareText((string) $a['title'], (string) $b['title']),
                'portal' => $factor * $this->compareText((string) $a['tenant'], (string) $b['tenant']),
                'status' => $factor * ($this->statusOrder((string) $a['status']) <=> $this->statusOrder((string) $b['status'])),
                'score' => $this->compareNullsLast($a['score'] ?? null, $b['score'] ?? null, $factor),
                default => $this->compareNullsLast($a['at'] ?? null, $b['at'] ?? null, $factor),
            };

            if ($result !== 0) {
                return $result;
            }

            $result = $this->compareNullsLast($a['score'] ?? null, $b['score'] ?? null, -1);

            return $result !== 0 ? $result : $this->compareText((string) $a['title'], (string) $b['title']);
        };
    }

    /**
     * Nullwerte immer ans Ende, sonst der uebliche Vergleich mal Richtung.
     */
    private function compareNullsLast(mixed $a, mixed $b, int $factor): int
    {
        $aEmpty = $a === null || $a === '';
        $bEmpty = $b === null || $b === '';

        if ($aEmpty && $bEmpty) {
            return 0;
        }

        if ($aEmpty) {
            return 1;
        }

        if ($bEmpty) {
            return -1;
        }

        return $factor * ($a <=> $b);
    }

    private function compareText(string $a, string $b): int
    {
        return strnatcasecmp($a, $b);
    }

    /**
     * Status sortiert in der Reihenfolge des Boards, nicht alphabetisch —
     * "Eingeplant" vor "In Erstellung" ist die Reihenfolge der Pipeline.
     * Status ausserhalb des Boards (fehlgeschlagen, zurueckgezogen) haengen
     * in ihrer Enum-Reihenfolge hinten an.
     */
    private function statusOrder(string $status): int
    {
        $board = array_flip(array_map(
            fn (DisplayStatus $case): string => $case->value,
            DisplayStatus::board(),
        ));

        if (isset($board[$status])) {
            return $board[$status];
        }

        return count($board) + (DisplayStatus::tryFrom($status)?->order() ?? DisplayStatus::ARCHIVED->order());
    }

    /**
     * Eine einzelne Karte samt Scores und Quellen fuer das Detailblatt.
     *
     * @return array<string, mixed>|null
     */
    public function cardDetails(string $key): ?array
    {
        $card = PipelineCardKey::parse($key);
        $tenant = $this->tenant($card->tenantId);

        if ($tenant === null) {
            return null;
        }

        return $this->run($tenant, function () use ($tenant, $card): ?array {
            if ($card->isDraft()) {
                $draft = ArticleDraft::query()->with('sources')->find($card->id);

                if ($draft === null) {
                    return null;
                }

                $base = $this->draftCard($tenant, $draft, $this->threshold(), $this->refresh->for($draft));

                return array_merge($base, [
                    // Herkunft einer Kindfassung (#99): Titel und Slug sind
                    // identisch, ohne diese Zeile ist sie von der
                    // Elternfassung nicht zu unterscheiden.
                    'origin' => $this->refresh->origin($draft),
                    'scores' => $this->draftScores($draft),
                    'sources' => $draft->sources
                        ->sortBy('sort_order')
                        ->map(fn (DraftSource $source): array => [
                            'title' => (string) $source->title,
                            'url' => $source->url,
                            'publisher' => $source->publisher,
                            'published_at' => $source->published_at?->format('d.m.Y'),
                            'is_cited' => (bool) $source->is_cited,
                        ])->values()->all(),
                    'keyword' => $draft->topicCandidate?->primary_keyword,
                    'withdrawn_reason' => $draft->withdrawn_reason,
                ]);
            }

            $topic = TopicCandidate::query()->find($card->id);

            if ($topic === null) {
                return null;
            }

            return array_merge($this->topicCard($tenant, $topic), [
                'scores' => $this->topicScores($topic),
                'sources' => $topic->sourceItems()
                    ->map(fn (SourceItem $item): array => [
                        'title' => (string) $item->title,
                        'url' => $item->url,
                        'publisher' => $item->source_key,
                        'published_at' => $item->published_at?->format('d.m.Y'),
                        'is_cited' => false,
                    ])->values()->all(),
                'keyword' => $topic->primary_keyword,
                'withdrawn_reason' => $topic->rejection_reason,
            ]);
        });
    }

    /*
    |--------------------------------------------------------------------------
    | Kalender
    |--------------------------------------------------------------------------
    */

    /**
     * Tageszeilen des Monats: Zaehlerstand und gestapelte Statusanteile.
     *
     * @param  array<string, mixed>  $filters
     * @return array<string, array<string, mixed>> Schluessel: Y-m-d
     */
    public function calendarDays(CarbonImmutable $from, CarbonImmutable $to, array $filters = []): array
    {
        $days = [];
        $target = 0;

        foreach ($this->tenants($filters) as $tenant) {
            $rows = $this->run($tenant, function () use ($from, $to, $filters): array {
                $settings = TenantContentSetting::query()->first();

                $rows = ArticleDraft::query()
                    ->tap(fn (Builder $query) => $this->applyRegionFilter($query, $filters))
                    ->selectRaw('DATE(COALESCE(published_at, scheduled_for)) as day, status, CASE WHEN withdrawn_at IS NULL THEN 0 ELSE 1 END as withdrawn, COUNT(*) as total')
                    ->whereRaw('COALESCE(published_at, scheduled_for) BETWEEN ? AND ?', [$from->startOfDay(), $to->endOfDay()])
                    ->groupByRaw('DATE(COALESCE(published_at, scheduled_for)), status, CASE WHEN withdrawn_at IS NULL THEN 0 ELSE 1 END')
                    // Rohzeilen statt Modelle: die Aggregation liefert keine
                    // Entwuerfe, und der Cast auf DraftStatus wuerde hier nur
                    // stoeren.
                    ->toBase()
                    ->get()
                    ->map(fn (object $row): array => [
                        'day' => (string) $row->day,
                        'status' => DisplayStatus::fromDraft(
                            DraftStatus::from((string) $row->status),
                            (bool) $row->withdrawn,
                        )->value,
                        'total' => (int) $row->total,
                    ])
                    ->all();

                return [
                    'target' => (int) ($settings?->articles_per_day
                        ?? config('content.targets.articles_per_tenant_per_day', 2)),
                    'rows' => $rows,
                ];
            }) ?? ['target' => 0, 'rows' => []];

            $target += $rows['target'];

            foreach ($rows['rows'] as $row) {
                $day = $row['day'];
                $days[$day] ??= ['counts' => [], 'total' => 0, 'published' => 0];
                $days[$day]['counts'][$row['status']] = ($days[$day]['counts'][$row['status']] ?? 0) + $row['total'];
                $days[$day]['total'] += $row['total'];

                if ($row['status'] === DisplayStatus::PUBLISHED->value) {
                    $days[$day]['published'] += $row['total'];
                }
            }
        }

        foreach ($days as $day => $data) {
            $days[$day]['target'] = $target;
        }

        return $days;
    }

    /**
     * Tagessoll ueber alle sichtbaren Portale — auch fuer Tage ohne Zeile.
     *
     * @param  array<string, mixed>  $filters
     */
    public function dailyTarget(array $filters = []): int
    {
        $target = 0;

        foreach ($this->tenants($filters) as $tenant) {
            $target += $this->run($tenant, fn (): int => (int) (TenantContentSetting::query()->value('articles_per_day')
                ?? config('content.targets.articles_per_tenant_per_day', 2))) ?? 0;
        }

        return $target;
    }

    /**
     * Einzelne Artikel eines Zeitraums — Wochenansicht und Tagesblatt.
     *
     * @param  array<string, mixed>  $filters
     * @return array<int, array<string, mixed>>
     */
    public function scheduleBetween(CarbonImmutable $from, CarbonImmutable $to, array $filters = []): array
    {
        $cards = [];

        foreach ($this->tenants($filters) as $tenant) {
            $rows = $this->run($tenant, function () use ($tenant, $from, $to, $filters): array {
                $threshold = $this->threshold();

                return ArticleDraft::query()
                    ->tap(fn (Builder $query) => $this->applyRegionFilter($query, $filters))
                    ->whereRaw('COALESCE(published_at, scheduled_for) BETWEEN ? AND ?', [$from, $to])
                    ->orderByRaw('COALESCE(published_at, scheduled_for)')
                    ->limit(self::PER_TENANT_LIMIT)
                    ->get()
                    ->map(fn (ArticleDraft $draft): array => $this->draftCard($tenant, $draft, $threshold))
                    ->all();
            }) ?? [];

            foreach ($rows as $row) {
                $cards[] = $row;
            }
        }

        $cards = $this->filterByStatus($cards, $filters);

        usort($cards, fn (array $a, array $b): int => [$a['at'], $a['tenant']] <=> [$b['at'], $b['tenant']]);

        return $cards;
    }

    /**
     * Verschiebt den Veroeffentlichungstermin eines Entwurfs.
     *
     * Erlaubt ist das ausschliesslich im Anzeige-Status "Eingeplant";
     * veroeffentlichte Artikel sind fixiert (Abnahmekriterium #19).
     *
     * @throws RuntimeException wenn der Entwurf nicht verschoben werden darf
     */
    public function reschedule(string $key, CarbonImmutable $target): string
    {
        $card = PipelineCardKey::parse($key);

        if (! $card->isDraft()) {
            throw new RuntimeException(__('Nur eingeplante Artikel haben einen Termin.'));
        }

        $tenant = $this->requireTenant($card->tenantId);

        return $tenant->run(function () use ($card, $target): string {
            $draft = ArticleDraft::query()->findOrFail($card->id);

            if ($draft->display_status !== DisplayStatus::SCHEDULED) {
                throw new RuntimeException(__('Nur eingeplante Artikel lassen sich verschieben.'));
            }

            // Uhrzeit beibehalten, wenn das Ziel nur ein Datum ist — die
            // gestaffelte Veroeffentlichung (#21) haengt an der Minute.
            $current = $draft->scheduled_for;
            $new = $target->hour === 0 && $target->minute === 0 && $current !== null
                ? $target->setTime($current->hour, $current->minute)
                : $target;

            $draft->forceFill(['scheduled_for' => $new])->save();

            return __('Termin auf :date verschoben.', ['date' => $new->format('d.m.Y H:i')]);
        });
    }

    /*
    |--------------------------------------------------------------------------
    | Handlungen
    |--------------------------------------------------------------------------
    */

    /**
     * Ablehnen: Thema verwerfen bzw. Entwurf zurueckziehen. Der Grund ist
     * Pflicht und fliesst spaeter in die Lernschleife (#23).
     */
    public function reject(string $key, string $reason, ?int $userId = null): string
    {
        $card = PipelineCardKey::parse($key);
        $tenant = $this->requireTenant($card->tenantId);

        return $tenant->run(function () use ($card, $reason, $userId): string {
            if ($card->isDraft()) {
                $draft = ArticleDraft::query()->findOrFail($card->id);

                if (! $draft->display_status->allowsWithdrawal()) {
                    throw new RuntimeException(__('Dieser Artikel lässt sich in seinem Status nicht zurückziehen.'));
                }

                $draft->withdraw($reason, $userId);

                return __('Artikel zurückgezogen.');
            }

            $topic = TopicCandidate::query()->findOrFail($card->id);

            if ($topic->status === TopicStatus::REJECTED) {
                throw new RuntimeException(__('Das Thema ist bereits verworfen.'));
            }

            $topic->forceFill([
                'status' => TopicStatus::REJECTED,
                'rejection_reason' => mb_substr($reason, 0, 255),
                'selected_for_date' => null,
            ])->save();

            return __('Thema verworfen.');
        });
    }

    /**
     * Reserve hochstufen: ein bewertetes Thema aus der Reserve in den Plan
     * des heutigen Tages heben.
     */
    public function promoteReserve(string $key): string
    {
        $card = PipelineCardKey::parse($key);

        if ($card->isDraft()) {
            throw new RuntimeException(__('Nur Themenvorschläge lassen sich hochstufen.'));
        }

        $tenant = $this->requireTenant($card->tenantId);

        return $tenant->run(function () use ($card): string {
            $topic = TopicCandidate::query()->findOrFail($card->id);

            if ($topic->status === TopicStatus::SELECTED) {
                throw new RuntimeException(__('Das Thema ist bereits eingeplant.'));
            }

            if ($topic->status === TopicStatus::REJECTED) {
                throw new RuntimeException(__('Ein verworfenes Thema lässt sich nicht hochstufen.'));
            }

            // discovered -> selected ist kein erlaubter Uebergang; ein noch
            // unbewertetes Thema wird deshalb zuerst als bewertet vermerkt.
            if ($topic->status === TopicStatus::DISCOVERED) {
                $topic->forceFill(['status' => TopicStatus::SCORED])->save();
            }

            $topic->forceFill([
                'status' => TopicStatus::SELECTED,
                'selected_for_date' => CarbonImmutable::today(),
            ])->save();

            return __('Thema für heute eingeplant.');
        });
    }

    /**
     * Jetzt generieren: Entwurf bzw. Thema sofort erzeugen lassen.
     *
     * Der eigentliche Weg steht in startGeneration(); diese Methode ist nur
     * der Eingang aus dem Board und der Artikelliste (#42, §1).
     */
    public function generateNow(string $key): GenerationStart
    {
        return $this->startGeneration($key);
    }

    /**
     * Einziger Einstiegspunkt der Sofort-Erzeugung (#42, §1).
     *
     * Prueft in fester Reihenfolge — laufende Erzeugung, Uebergang, Versuche,
     * Budget — und bricht bei jedem Befund ohne Zustandswechsel ab. Erst
     * danach werden Zustand und Startzeitpunkt gesetzt und der Generatorjob
     * eingereiht.
     *
     * Der Hinweis aus der Pruefung wird hier nicht abgelegt: das tut
     * ReviewQueueService, bevor er hierher abbiegt — er ist Pruefungslogik.
     * Der Versuchszaehler dagegen wandert genau hier hoch, an einer Stelle.
     */
    public function startGeneration(string $key, ?string $fixInstruction = null): GenerationStart
    {
        $card = PipelineCardKey::parse($key);
        $tenant = $this->requireTenant($card->tenantId);

        return $tenant->run(function () use ($card, $fixInstruction): GenerationStart {
            if ($card->isDraft()) {
                return $this->startDraftGeneration($card->tenantId, (int) $card->id, $fixInstruction);
            }

            return $this->startTopicGeneration($card->tenantId, (int) $card->id, $fixInstruction);
        });
    }

    private function startDraftGeneration(int $tenantId, int $draftId, ?string $fixInstruction): GenerationStart
    {
        $draft = ArticleDraft::query()->findOrFail($draftId);

        // Vor dem Uebergang: ein Entwurf, dessen Lauf noch laeuft, wuerde vom
        // ShouldBeUnique des Generators still verworfen (#42, §2a). Lieber
        // derselbe Satz in der Oberflaeche als eine Karte, die ewig steht.
        if ($this->isGenerationRunning($draft)) {
            throw new RuntimeException(__('Für diesen Artikel läuft bereits eine Erzeugung. Bitte das Ergebnis abwarten.'));
        }

        if (! $draft->status->canTransitionTo(DraftStatus::GENERATING)) {
            throw new RuntimeException(__('Aus diesem Status heraus lässt sich nicht neu erzeugen.'));
        }

        $maxAttempts = (int) config('content.pipeline.max_generation_attempts', 2);

        if ($draft->attempt >= $maxAttempts) {
            throw new RuntimeException(__('Die erlaubten :count Versuche sind aufgebraucht. Bitte verwerfen.', ['count' => $maxAttempts]));
        }

        $this->assertBudget($tenantId, $draftId);

        $report = $draft->quality_report_json ?? [];
        $generation = (array) ($report['generation'] ?? []);
        $generation['started_at'] = now()->toIso8601String();
        $report['generation'] = $generation;

        $draft->forceFill([
            'quality_report_json' => $report,
            'attempt' => $draft->attempt + 1,
        ])->save();

        $draft->transitionTo(DraftStatus::GENERATING);

        return $this->result(
            $this->dispatchGeneration($tenantId, $draftId, null),
            $fixInstruction,
        );
    }

    private function startTopicGeneration(int $tenantId, int $topicId, ?string $fixInstruction): GenerationStart
    {
        $topic = TopicCandidate::query()->findOrFail($topicId);

        if ($topic->status === TopicStatus::REJECTED) {
            throw new RuntimeException(__('Ein verworfenes Thema wird nicht erzeugt.'));
        }

        // Themen fuehren keinen Versuchszaehler; der Schritt entfaellt.
        $this->assertBudget($tenantId, null);

        // discovered -> selected ist kein erlaubter Uebergang; ein noch
        // unbewertetes Thema wird deshalb zuerst als bewertet vermerkt.
        if ($topic->status === TopicStatus::DISCOVERED) {
            $topic->forceFill(['status' => TopicStatus::SCORED])->save();
        }

        $topic->forceFill([
            'status' => TopicStatus::SELECTED,
            'selected_for_date' => CarbonImmutable::today(),
        ])->save();

        return $this->result(
            $this->dispatchGeneration($tenantId, null, $topicId),
            $fixInstruction,
        );
    }

    /**
     * Laeuft fuer diesen Entwurf gerade eine Erzeugung? Grundlage ist der
     * Startzeitpunkt im Qualitaetsbericht und die Sperrfrist des Generators
     * (uniqueFor() = 7200 Sekunden).
     */
    private function isGenerationRunning(ArticleDraft $draft): bool
    {
        if ($draft->status !== DraftStatus::GENERATING) {
            return false;
        }

        $startedAt = $this->generationStartedAt($draft);

        return $startedAt !== null && $startedAt->greaterThan(now()->subSeconds(self::GENERATION_LOCK_SECONDS));
    }

    private function generationStartedAt(ArticleDraft $draft): ?CarbonImmutable
    {
        $report = $draft->quality_report_json;
        $generation = is_array($report) ? ($report['generation'] ?? null) : null;
        $value = is_array($generation) ? ($generation['started_at'] ?? null) : null;

        if (! is_string($value) || $value === '') {
            return null;
        }

        try {
            return CarbonImmutable::parse($value);
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * Budgetgrenze vor dem Zustandswechsel. Die technische Meldung der
     * Ausnahme gehoert ins Log, nicht in die Oberflaeche (#42, §3).
     */
    private function assertBudget(int $tenantId, ?int $draftId): void
    {
        try {
            app(BudgetGuard::class)->check(
                'anthropic',
                $tenantId,
                $draftId !== null ? 'article_drafts' : null,
                $draftId,
            );
        } catch (BudgetExceededException $exception) {
            Log::warning('Sofort-Erzeugung: Budgetgrenze erreicht.', [
                'scope' => $exception->scope,
                'tenant_id' => $tenantId,
                'draft_id' => $draftId,
                'message' => $exception->getMessage(),
            ]);

            throw new RuntimeException(match ($exception->scope) {
                BudgetExceededException::SCOPE_TENANT => __('Für dieses Portal ist das Tagesbudget aufgebraucht. Andere Portale erzeugen weiter.'),
                BudgetExceededException::SCOPE_ARTICLE => __('Für diesen Artikel ist die Kostengrenze erreicht. Bitte verwerfen oder morgen erneut versuchen.'),
                default => __('Das Tagesbudget für die Texterstellung ist aufgebraucht. Ab 00:00 läuft die Erzeugung wieder.'),
            });
        }
    }

    /**
     * Generatorjob einreihen. Muster wie ReviewQueueService::dispatchFollowUps():
     * fehlt der Job, bleibt es beim gesetzten Zustand, aus dem der naechste
     * Lauf ohnehin zieht. Die Queue setzt der Job selbst.
     */
    private function dispatchGeneration(int $tenantId, ?int $draftId, ?int $topicId): bool
    {
        $job = 'App\\Content\\Jobs\\GenerateDraftJob';

        if (! class_exists($job)) {
            // Umgebung ohne Generator: der Zustand ist gesetzt, der naechste
            // Lauf zieht daraus. Die Zeile macht das nachvollziehbar.
            Log::info('Sofort-Erzeugung: Generatorjob nicht vorhanden, nur vorgemerkt.', [
                'job' => $job,
                'tenant_id' => $tenantId,
                'draft_id' => $draftId,
                'topic_id' => $topicId,
            ]);

            return false;
        }

        try {
            $job::dispatch($tenantId, $draftId, $topicId);

            return true;
        } catch (Throwable $exception) {
            Log::error('Sofort-Erzeugung: Generatorjob konnte nicht angestossen werden.', [
                'job' => $job,
                'tenant_id' => $tenantId,
                'draft_id' => $draftId,
                'topic_id' => $topicId,
                'exception' => $exception->getMessage(),
            ]);

            return false;
        }
    }

    private function result(bool $queued, ?string $fixInstruction): GenerationStart
    {
        if (! $queued) {
            return GenerationStart::deferred(__('Vorgemerkt. Der nächste Generatorlauf übernimmt den Artikel.'));
        }

        return GenerationStart::queued($fixInstruction !== null
            ? __('Hinweis übergeben, Überarbeitung gestartet.')
            : __('Erzeugung gestartet. Der Artikel steht in wenigen Minuten in der Prüfung.'));
    }

    /**
     * Vorab-Abfrage des Budgets fuer einen Seitenaufbau (#42, §4).
     *
     * Eine Abfrage je Seite, nicht je Karte: das Board zeigt bis zu 50 Karten
     * je Spalte. Das Ergebnis traegt den deaktivierten Auslöser samt
     * Begruendung und den Warnsatz im Bestaetigungsdialog.
     *
     * @return array{available: bool, tight: bool, reason: ?string, limit_usd: float}
     */
    public function generationBudget(): array
    {
        if ($this->generationBudget !== null) {
            return $this->generationBudget;
        }

        $limit = (float) config('content.budget.max_usd_per_article', 0.0);

        try {
            $guard = app(BudgetGuard::class);
            $remaining = $guard->remainingFor('anthropic');
            $available = $remaining > 0.0 && ! $guard->stateFor('anthropic')->isPaused();
            $tight = $available && $remaining < $limit;
        } catch (Throwable $exception) {
            Log::warning('Sofort-Erzeugung: Budgetstand nicht lesbar.', [
                'exception' => $exception->getMessage(),
            ]);

            // Ohne lesbaren Stand nicht sperren: eine Handlung zu verbieten,
            // weil eine Nebenabfrage scheitert, ist der schlechtere Fehler.
            $available = true;
            $tight = false;
        }

        return $this->generationBudget = [
            'available' => $available,
            'tight' => $tight,
            'reason' => $available ? null : __('Tagesbudget aufgebraucht. Ab 00:00 wird wieder erzeugt.'),
            'limit_usd' => $limit,
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | Abbildung auf Karten
    |--------------------------------------------------------------------------
    */

    /**
     * @param  array<string, mixed>  $filters
     * @return array<int, array<string, mixed>>
     */
    private function tenantCards(Tenant $tenant, array $filters): array
    {
        $cards = $this->run($tenant, function () use ($tenant, $filters): array {
            $threshold = $this->threshold();

            $models = ArticleDraft::query()
                ->with('topicCandidate:id,primary_keyword')
                ->tap(fn (Builder $query) => $this->applyRegionFilter($query, $filters))
                ->orderByDesc('updated_at')
                ->limit(self::PER_TENANT_LIMIT)
                ->get();

            // Aktualisierungsstand (#99) fuer alle Zeilen auf einmal: eine
            // Abfrage je Portal statt zweier je Zeile.
            $states = $this->refresh->forDrafts($models);

            $drafts = $models
                ->map(fn (ArticleDraft $draft): array => $this->draftCard(
                    $tenant,
                    $draft,
                    $threshold,
                    $states[(int) $draft->getKey()] ?? null,
                ))
                ->all();

            $topics = TopicCandidate::query()
                ->whereIn('status', [TopicStatus::DISCOVERED->value, TopicStatus::SCORED->value, TopicStatus::RESERVE->value, TopicStatus::SELECTED->value])
                ->tap(fn (Builder $query) => $this->applyRegionFilter($query, $filters))
                ->orderByDesc('total_score')
                ->limit(self::PER_TENANT_LIMIT)
                ->get()
                ->map(fn (TopicCandidate $topic): array => $this->topicCard($tenant, $topic))
                ->all();

            return array_merge($drafts, $topics);
        }) ?? [];

        return $this->filterByStatus($cards, $filters);
    }

    /**
     * @return array<string, mixed>
     */
    private function draftCard(Tenant $tenant, ArticleDraft $draft, int $threshold, ?array $refresh = null): array
    {
        $status = $draft->display_status;
        $at = $draft->published_at ?? $draft->scheduled_for;

        return [
            'key' => PipelineCardKey::draft((int) $tenant->getKey(), (int) $draft->getKey())->toString(),
            'type' => PipelineCardKey::TYPE_DRAFT,
            'tenant_id' => (int) $tenant->getKey(),
            'tenant' => (string) $tenant->name,
            'branch' => BranchResolver::resolve($tenant),
            'title' => (string) $draft->title,
            'status' => $status->value,
            'status_label' => $status->label(),
            'progress' => DisplayStatus::progressLabel($draft->status),
            'score' => $draft->quality_score !== null ? (float) $draft->quality_score : null,
            'score_level' => $this->scoreLevel($draft->quality_score, $threshold),
            'region' => $this->regionLabel($draft->region_scope, $draft->region_code),
            'region_key' => $draft->region_scope === 'national' ? 'national' : (string) $draft->region_code,
            'at' => $at?->toDateTimeString(),
            'date' => $at?->toDateString(),
            'time' => $at?->format('H:i'),
            'movable' => $status === DisplayStatus::SCHEDULED,
            'attempt' => (int) $draft->attempt,
            'running_since' => $status === DisplayStatus::GENERATING
                ? $this->generationStartedAt($draft)?->format('H:i')
                : null,
            // Aktualisierungsstand (#99): null heisst "kein Zustand", die
            // Spalte bleibt dann leer und der Streifen entfaellt.
            'refresh' => $refresh,
            'is_refresh' => $draft->parent_draft_id !== null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function topicCard(Tenant $tenant, TopicCandidate $topic): array
    {
        $status = $topic->displayStatus();

        return [
            'key' => PipelineCardKey::topic((int) $tenant->getKey(), (int) $topic->getKey())->toString(),
            'type' => PipelineCardKey::TYPE_TOPIC,
            'tenant_id' => (int) $tenant->getKey(),
            'tenant' => (string) $tenant->name,
            'branch' => BranchResolver::resolve($tenant),
            'title' => (string) $topic->title,
            'status' => $status->value,
            'status_label' => $status->label(),
            'progress' => $topic->status === TopicStatus::DISCOVERED ? __('Noch nicht bewertet') : null,
            'refresh' => null,
            'is_refresh' => false,
            'score' => $topic->total_score !== null ? (float) $topic->total_score : null,
            'score_level' => null,
            'region' => $this->regionLabel($topic->region_scope, $topic->region_code),
            'region_key' => $topic->region_scope === 'national' ? 'national' : (string) $topic->region_code,
            'at' => $topic->selected_for_date?->toDateTimeString(),
            'date' => $topic->selected_for_date?->toDateString(),
            'time' => null,
            'movable' => false,
            'attempt' => 0,
            'running_since' => null,
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function draftScores(ArticleDraft $draft): array
    {
        $report = $draft->quality_report_json ?? [];
        $scores = [];

        foreach (($report['rubrics'] ?? []) as $name => $value) {
            $scores[] = [
                'label' => is_string($name) ? $name : (string) ($value['label'] ?? ''),
                'value' => (float) (is_array($value) ? ($value['score'] ?? 0) : $value),
                'note' => is_array($value) ? ($value['reason'] ?? null) : null,
            ];
        }

        if ($scores === [] && $draft->quality_score !== null) {
            $scores[] = ['label' => __('Gesamtscore'), 'value' => (float) $draft->quality_score, 'note' => null];
        }

        return $scores;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function topicScores(TopicCandidate $topic): array
    {
        return [
            ['label' => __('Gesamt'), 'value' => (float) $topic->total_score, 'note' => null],
            ['label' => __('Trend'), 'value' => (float) $topic->trend_score, 'note' => null],
            ['label' => __('Nachfrage'), 'value' => (float) $topic->demand_score, 'note' => null],
            ['label' => __('Lücke'), 'value' => (float) $topic->gap_score, 'note' => null],
            ['label' => __('Saison'), 'value' => (float) $topic->seasonal_score, 'note' => null],
            ['label' => __('Eigenständigkeit'), 'value' => (float) $topic->uniqueness_score, 'note' => null],
        ];
    }

    private function scoreLevel(?float $score, int $threshold): ?string
    {
        if ($score === null) {
            return null;
        }

        return match (true) {
            $score >= $threshold => 'good',
            $score >= $threshold * 0.75 => 'mid',
            default => 'poor',
        };
    }

    private function threshold(): int
    {
        return (int) (TenantContentSetting::query()->value('auto_publish_threshold') ?: 80);
    }

    public function regionLabel(?string $scope, ?string $code): string
    {
        if ($scope === 'national' || $code === null || $code === '') {
            return __('Bundesweit');
        }

        $states = (array) config('content.regions.states', []);

        return (string) ($states[$code]['name'] ?? $code);
    }

    /**
     * Regionen fuer den Filter: bundesweit plus die Bundeslaender.
     *
     * @return array<string, string>
     */
    public function regionOptions(): array
    {
        $options = ['national' => __('Bundesweit')];

        foreach ((array) config('content.regions.states', []) as $code => $state) {
            $options[(string) $code] = (string) ($state['name'] ?? $code);
        }

        return $options;
    }

    /*
    |--------------------------------------------------------------------------
    | Filter und Tenant-Kontext
    |--------------------------------------------------------------------------
    */

    /**
     * @param  array<string, mixed>  $filters
     */
    private function applyRegionFilter(Builder $query, array $filters): void
    {
        $regions = array_values(array_filter((array) ($filters['regions'] ?? [])));

        if ($regions === []) {
            return;
        }

        $codes = array_values(array_filter($regions, fn (string $region): bool => $region !== 'national'));
        $national = in_array('national', $regions, true);

        $query->where(function (Builder $inner) use ($codes, $national): void {
            if ($national) {
                $inner->orWhere('region_scope', 'national');
            }

            if ($codes !== []) {
                $inner->orWhereIn('region_code', $codes);
            }
        });
    }

    /**
     * Statusfilter greift auf dem Anzeige-Status und deshalb erst nach der
     * Abbildung — DraftStatus und TopicStatus lassen sich nicht 1:1 in eine
     * WHERE-Klausel uebersetzen.
     *
     * @param  array<int, array<string, mixed>>  $cards
     * @param  array<string, mixed>  $filters
     * @return array<int, array<string, mixed>>
     */
    private function filterByStatus(array $cards, array $filters): array
    {
        $statuses = array_values(array_filter((array) ($filters['statuses'] ?? [])));

        if ($statuses === []) {
            // Zurueckgezogene Artikel sind im Board keine Spalte und tauchen
            // nur ueber den ausdruecklichen Statusfilter auf.
            return array_values(array_filter(
                $cards,
                fn (array $card): bool => $card['status'] !== DisplayStatus::ARCHIVED->value,
            ));
        }

        return array_values(array_filter(
            $cards,
            fn (array $card): bool => in_array($card['status'], $statuses, true),
        ));
    }

    /**
     * Sichtbare Portale: Zuordnung des Accounts, globale Portalauswahl und
     * der Portalfilter der Ansicht.
     *
     * @param  array<string, mixed>  $filters
     * @return Collection<int, Tenant>
     */
    public function tenants(array $filters = []): Collection
    {
        /** @var Collection<int, Tenant> $tenants */
        $tenants = collect($this->context->available()->all());
        $selected = $this->context->selectedId();

        if ($selected !== null) {
            $tenants = $tenants->filter(fn (Tenant $tenant): bool => (int) $tenant->getKey() === $selected);
        }

        $portals = array_map('intval', array_filter((array) ($filters['portals'] ?? [])));

        if ($portals !== []) {
            $tenants = $tenants->filter(fn (Tenant $tenant): bool => in_array((int) $tenant->getKey(), $portals, true));
        }

        $branches = array_values(array_filter((array) ($filters['branches'] ?? [])));

        if ($branches !== []) {
            $tenants = $tenants->filter(fn (Tenant $tenant): bool => in_array(BranchResolver::resolve($tenant), $branches, true));
        }

        return $tenants->values();
    }

    private function tenant(int $id): ?Tenant
    {
        return $this->tenants()->first(fn (Tenant $tenant): bool => (int) $tenant->getKey() === $id);
    }

    private function requireTenant(int $id): Tenant
    {
        return $this->tenant($id) ?? throw new RuntimeException(__('Für dieses Portal fehlt die Berechtigung.'));
    }

    /**
     * Tenant-Durchgang, der eine fehlende Pipeline-Tabelle nicht zum
     * Seitenfehler macht (Staging, frisch angelegtes Portal).
     *
     * @template TReturn
     *
     * @param  callable(): TReturn  $callback
     * @return TReturn|null
     */
    private function run(Tenant $tenant, callable $callback)
    {
        try {
            return $tenant->run($callback);
        } catch (Throwable $exception) {
            $this->unreadableTenants++;

            Log::warning('Content-Produktion: Portal uebersprungen.', [
                'tenant' => $tenant->getKey(),
                'message' => $exception->getMessage(),
            ]);

            return null;
        }
    }
}
