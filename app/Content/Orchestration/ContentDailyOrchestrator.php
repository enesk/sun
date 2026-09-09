<?php

declare(strict_types=1);

namespace App\Content\Orchestration;

use App\Content\Enums\DraftStatus;
use App\Content\Enums\TopicStatus;
use App\Content\Jobs\DailyChainJob;
use App\Content\Jobs\DiscoverTopicsJob;
use App\Content\Jobs\GenerateDraftJob;
use App\Content\Jobs\ScheduleAndPublishJob;
use App\Content\Jobs\ScoreTopicsJob;
use App\Content\Jobs\SelectDailyTopicsJob;
use App\Content\Models\ArticleDraft;
use App\Content\Models\Central\ContentAlert;
use App\Content\Models\TenantContentSetting;
use App\Content\Models\TopicCandidate;
use App\Content\Services\TenantRollout;
use App\Models\Tenant;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Der Tages-Orchestrator der Content-Pipeline (#22).
 *
 * Er haelt die Uhrzeiten des Tages zusammen, nicht die Reihenfolge der
 * Einzelschritte: innerhalb eines Artikels stoesst jeder Job seinen
 * Nachfolger selbst an, nachdem sein Ergebnis persistiert ist
 * (Generator -> Qualitaetsgate -> Assets -> Veroeffentlichung). Nur die
 * Themenstufen, die ohne Artikelbezug laufen, haengen hier als Bus-Chain
 * zusammen — sie sind eine Einheit und duerfen nicht halb laufen.
 *
 * Jede Stufe laeuft je Mandant getrennt (DailyChainJob). Faellt ein Mandant
 * aus, bekommt er einen Alarm; die uebrigen laufen weiter.
 */
final class ContentDailyOrchestrator
{
    public const STAGE_DISCOVER = 'discover';

    public const STAGE_SELECT = 'select';

    public const STAGE_GENERATE = 'generate';

    public const STAGE_PUBLISH = 'publish';

    /**
     * Reihenfolge des Tages. `content:daily` ohne --stage laeuft sie in
     * genau dieser Folge durch.
     *
     * @var array<int, string>
     */
    public const STAGES = [
        self::STAGE_DISCOVER,
        self::STAGE_SELECT,
        self::STAGE_GENERATE,
        self::STAGE_PUBLISH,
    ];

    public function __construct(private readonly TenantRollout $rollout) {}

    /**
     * Meldung fuer ein Portal, das noch nicht freigeschaltet ist (#26).
     * Kein Fehler, sondern der Normalzustand vor dem Go-Live.
     */
    public static function skippedMessage(): string
    {
        return __('Portal ist für die Content-Pipeline nicht freigeschaltet.');
    }

    /**
     * Eine Stufe fuer alle freigeschalteten Mandanten einreihen — je Mandant
     * ein eigener DailyChainJob, damit ein Ausfall die uebrigen nicht
     * mitnimmt. Portale ohne Rollout-Schalter (#26) bleiben aussen vor.
     *
     * @return int Zahl der angestossenen Mandanten
     */
    public function dispatchStageForAll(string $stage, ?CarbonImmutable $date = null): int
    {
        $count = 0;

        foreach ($this->rollout->activeTenants() as $tenant) {
            DailyChainJob::dispatch((int) $tenant->getKey(), $stage, $this->date($date)->toDateString());
            $count++;
        }

        return $count;
    }

    /**
     * Eine Stufe fuer einen Mandanten. Rueckgabe ist die Klartextmeldung
     * fuer Konsole und Log. Ein nicht freigeschaltetes Portal (#26) wird
     * uebersprungen, ohne dass ein Job entsteht.
     */
    public function runStage(Tenant $tenant, string $stage, ?CarbonImmutable $date = null, bool $sync = false): string
    {
        $date = $this->date($date);

        if (! $this->rollout->isActive($tenant)) {
            return self::skippedMessage();
        }

        return match ($stage) {
            self::STAGE_DISCOVER => $this->discover($tenant, $sync),
            self::STAGE_SELECT => $this->select($tenant, $date, $sync),
            self::STAGE_GENERATE => $this->generate($tenant, $date, $sync),
            self::STAGE_PUBLISH => $this->publish($tenant, $sync),
            default => throw new \InvalidArgumentException("Unbekannte Stufe: {$stage}"),
        };
    }

    /**
     * Die komplette Tageskette eines Mandanten — der Weg zum Nachholen und
     * zur Fehlersuche (`content:daily`).
     *
     * @return array<string, string>
     */
    public function runDay(Tenant $tenant, ?CarbonImmutable $date = null, bool $sync = false): array
    {
        $result = [];

        foreach (self::STAGES as $stage) {
            $result[$stage] = $this->runStage($tenant, $stage, $date, $sync);
        }

        return $result;
    }

    /**
     * Themenfindung und Scoring. Beide gehoeren zusammen: unbewertete
     * Kandidaten sind fuer die Tagesauswahl wertlos. Deshalb hier die
     * einzige Bus-Chain der Pipeline — mit ->catch(), das den Ausfall als
     * Alarm sichtbar macht.
     */
    private function discover(Tenant $tenant, bool $sync): string
    {
        $tenantId = (int) $tenant->getKey();

        if ($sync) {
            DiscoverTopicsJob::dispatchSync($tenantId, false);
            ScoreTopicsJob::dispatchSync($tenantId, false);

            return __('Themen gefunden und bewertet.');
        }

        $name = (string) $tenant->name;

        Bus::chain([
            new DiscoverTopicsJob($tenantId, false),
            new ScoreTopicsJob($tenantId, false),
        ])->catch(function (Throwable $exception) use ($tenantId, $name): void {
            ContentAlert::raise(
                ContentAlert::KEY_CHAIN_FAILED,
                __('Themenfindung für :portal abgebrochen.', ['portal' => $name]),
                $tenantId,
                ContentAlert::LEVEL_CRITICAL,
                ['stage' => self::STAGE_DISCOVER, 'exception' => $exception->getMessage()],
                CarbonImmutable::today(),
            );
        })->dispatch();

        return __('Themenfindung und Bewertung eingereiht.');
    }

    /**
     * Tagesauswahl fuer genau diesen Tag. Der Job waehlt ohne Datum fuer
     * morgen aus; der Tageslauf braucht sie fuer heute.
     */
    private function select(Tenant $tenant, CarbonImmutable $date, bool $sync): string
    {
        $tenantId = (int) $tenant->getKey();

        $sync
            ? SelectDailyTopicsJob::dispatchSync($tenantId, $date->toDateString())
            : SelectDailyTopicsJob::dispatch($tenantId, $date->toDateString());

        return __('Tagesauswahl für :date angestoßen.', ['date' => $date->toDateString()]);
    }

    /**
     * Ein Generatorlauf je Slot. Qualitaetsgate, Assets und Veroeffentlichung
     * haengen am Generator selbst — hier wird nur der Einstieg gesetzt.
     */
    private function generate(Tenant $tenant, CarbonImmutable $date, bool $sync): string
    {
        $slots = $this->openSlots($tenant, $date);

        if ($slots === []) {
            return __('Nichts zu erzeugen: alle Slots sind belegt oder es fehlen Themen.');
        }

        foreach ($slots as $slot => $topicId) {
            $sync
                ? GenerateDraftJob::dispatchSync((int) $tenant->getKey(), null, $topicId, $slot)
                : GenerateDraftJob::dispatch((int) $tenant->getKey(), null, $topicId, $slot);
        }

        return trans_choice(
            '{1}Ein Artikel angestoßen.|[2,*]:count Artikel angestoßen.',
            count($slots),
            ['count' => count($slots)],
        );
    }

    /**
     * Einplanen und Veroeffentlichen aller freigegebenen Entwuerfe. Der
     * Normalfall laeuft ueber die Kette; dieser Aufruf holt nach, was liegen
     * geblieben ist.
     */
    private function publish(Tenant $tenant, bool $sync): string
    {
        $sync
            ? ScheduleAndPublishJob::dispatchSync((int) $tenant->getKey())
            : ScheduleAndPublishJob::dispatch((int) $tenant->getKey());

        return __('Veröffentlichung angestoßen.');
    }

    /**
     * Die noch unbelegten Slots des Tages mit dem Thema, das sie fuellen
     * soll: Slot-Nummer => topic_candidates.id.
     *
     * Ein Slot gilt als belegt, sobald ein nicht gescheiterter Entwurf des
     * Tages daran haengt — ein zweiter Anlauf waere ein doppelter Artikel.
     * Fehlen fuer den Tag Themen, entsteht ein Alarm statt einer stillen
     * Luecke.
     *
     * @return array<int, int>
     */
    private function openSlots(Tenant $tenant, CarbonImmutable $date): array
    {
        $tenantId = (int) $tenant->getKey();

        /** @var array{target: int, taken: array<int, int>, topics: array<int, int>} $state */
        $state = $tenant->run(function () use ($date): array {
            $settings = TenantContentSetting::current();
            $target = max(1, (int) ($settings->articles_per_day ?? config('content.targets.articles_per_tenant_per_day', 2)));

            return [
                'target' => $target,
                'taken' => SlotWatchdog::coveredSlots($date),
                'topics' => TopicCandidate::query()
                    ->selectedFor($date)
                    ->orderByDesc('total_score')
                    ->limit($target)
                    ->pluck('id')
                    ->map('intval')
                    ->all(),
            ];
        });

        $topics = $state['topics'];
        $slots = [];

        for ($slot = 1; $slot <= $state['target']; $slot++) {
            if (in_array($slot, $state['taken'], true)) {
                continue;
            }

            $topicId = array_shift($topics);

            if ($topicId === null) {
                ContentAlert::raise(
                    ContentAlert::KEY_NO_TOPICS,
                    __('Für :portal fehlt ein ausgewähltes Thema für den :slot. Artikel des Tages.', [
                        'portal' => (string) $tenant->name,
                        'slot' => $slot,
                    ]),
                    $tenantId,
                    ContentAlert::LEVEL_WARNING,
                    ['stage' => self::STAGE_GENERATE, 'slot' => $slot],
                    $date,
                    $slot,
                );

                Log::warning('Tageslauf: kein Thema fuer den Slot.', [
                    'tenant_id' => $tenantId,
                    'date' => $date->toDateString(),
                    'slot' => $slot,
                ]);

                continue;
            }

            $slots[$slot] = $topicId;
        }

        return $slots;
    }

    private function date(?CarbonImmutable $date): CarbonImmutable
    {
        return ($date ?? CarbonImmutable::today())->startOfDay();
    }

    /**
     * Statuswerte, die einen Slot als belegt gelten lassen. Gescheiterte
     * Entwuerfe sind bewusst nicht dabei: fuer sie zieht der Watchdog einen
     * Reserve-Kandidaten.
     *
     * @return array<int, string>
     */
    public static function coveringStatuses(): array
    {
        return array_values(array_map(
            static fn (DraftStatus $status): string => $status->value,
            array_filter(
                DraftStatus::cases(),
                static fn (DraftStatus $status): bool => $status !== DraftStatus::FAILED,
            ),
        ));
    }

    /**
     * Der beste Reserve-Kandidat des Tages, bereits auf „ausgewaehlt"
     * gesetzt. Laeuft im Tenant-Kontext.
     */
    public static function claimReserveTopic(CarbonImmutable $date): ?int
    {
        $topic = TopicCandidate::query()
            ->reserveFor($date)
            ->orderByDesc('total_score')
            ->first();

        if ($topic === null) {
            return null;
        }

        $topic->forceFill([
            'status' => TopicStatus::SELECTED->value,
            'selected_for_date' => $date->toDateString(),
        ])->save();

        return (int) $topic->getKey();
    }

    /**
     * Entwuerfe des Tages. Massgeblich ist der Anlagetag: geplante und
     * veroeffentlichte Zeitpunkte koennen in der Zukunft liegen.
     *
     * @return \Illuminate\Database\Eloquent\Collection<int, ArticleDraft>
     */
    public static function draftsOfDay(CarbonImmutable $date)
    {
        return ArticleDraft::query()
            ->notWithdrawn()
            ->whereBetween('created_at', [$date->startOfDay(), $date->endOfDay()])
            ->orderBy('id')
            ->get();
    }
}
