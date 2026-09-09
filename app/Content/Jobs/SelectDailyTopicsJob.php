<?php

declare(strict_types=1);

namespace App\Content\Jobs;

use App\Content\Enums\DraftStatus;
use App\Content\Enums\TopicStatus;
use App\Content\Models\ArticleDraft;
use App\Content\Models\TenantContentSetting;
use App\Content\Models\TopicCandidate;
use App\Models\Tenant;
use Carbon\CarbonImmutable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Tagesauswahl je Mandant (#12): was morgen geschrieben wird.
 *
 * Gewaehlt werden `articles_per_day` Themen fuer den Folgetag, dazu die
 * doppelte Menge als Reserve. Die Reserve ist kein Beiwerk: schlaegt eine
 * Generierung fehl (#14) oder faellt ein Entwurf durchs Qualitaetsgate (#15),
 * rueckt ein Reservethema nach, ohne dass ein zweiter Modellaufruf fuer die
 * Themenfindung noetig wird.
 *
 * Ausgewaehlt wird gierig nach Gesamtscore, aber mit Diversitaetsstrafe: ein
 * bereits gewaehltes Cluster und eine bereits gewaehlte Region senken den
 * Auswahlwert der uebrigen Kandidaten. Zwei Ratgeber am selben Tag zum selben
 * Cluster wuerden sich gegenseitig kannibalisieren, zwei zur selben Stadt
 * sehen nach Doorway-Serie aus.
 *
 * Idempotent: liegen fuer den Zieltag schon Entwuerfe in Arbeit, wird die
 * Auswahl nicht angefasst. Ohne Entwuerfe wird eine bestehende Auswahl nur
 * ergaenzt, nie umgeworfen — sonst wechselt der Redaktionskalender unter der
 * Hand seinen Inhalt.
 */
class SelectDailyTopicsJob implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public int $timeout = 300;

    public function __construct(
        public int $tenantId,
        public ?string $forDate = null,
    ) {
        $this->onQueue((string) config('content.pipeline.queues.discovery', 'content-discovery'));
    }

    public function uniqueId(): string
    {
        return 'content-select-topics:'.$this->tenantId.':'.($this->forDate ?? 'tomorrow');
    }

    public function uniqueFor(): int
    {
        return 3600;
    }

    public function handle(): void
    {
        $tenant = Tenant::query()->find($this->tenantId);

        if ($tenant === null) {
            return;
        }

        $date = $this->forDate !== null
            ? CarbonImmutable::parse($this->forDate)->startOfDay()
            : CarbonImmutable::tomorrow();

        $tenant->run(function () use ($date): void {
            $settings = TenantContentSetting::current();
            $perDay = max(1, (int) $settings->articles_per_day);
            $reserveCount = $perDay * max(0, (int) config('content.topics.selection.reserve_factor', 2));

            if ($this->hasWorkInProgress($date)) {
                Log::info('Tagesauswahl unveraendert: Entwuerfe sind bereits in Arbeit.', [
                    'tenant_id' => $this->tenantId,
                    'date' => $date->toDateString(),
                ]);

                return;
            }

            $selected = TopicCandidate::query()->selectedFor($date)->get();
            $reserve = TopicCandidate::query()->reserveFor($date)->get();

            $pool = $this->pool($date);
            $taken = $selected->concat($reserve);

            $newSelected = $this->pick($pool, $taken, max(0, $perDay - $selected->count()));
            $taken = $taken->concat(collect($newSelected));

            $newReserve = $this->pick($pool, $taken, max(0, $reserveCount - $reserve->count()));

            DB::transaction(function () use ($newSelected, $newReserve, $date): void {
                foreach ($newSelected as $topic) {
                    $topic->forceFill([
                        'status' => TopicStatus::SELECTED,
                        'selected_for_date' => $date->toDateString(),
                    ])->save();
                }

                foreach ($newReserve as $topic) {
                    $topic->forceFill([
                        'status' => TopicStatus::RESERVE,
                        'selected_for_date' => $date->toDateString(),
                    ])->save();
                }
            });

            Log::info('Tagesauswahl abgeschlossen.', [
                'tenant_id' => $this->tenantId,
                'date' => $date->toDateString(),
                'selected' => $selected->count() + count($newSelected),
                'reserve' => $reserve->count() + count($newReserve),
                'pool' => $pool->count(),
            ]);
        });
    }

    /**
     * Liegen fuer den Zieltag schon Entwuerfe in Arbeit oder fertig? Dann ist
     * die Auswahl bereits verarbeitet und darf sich nicht mehr aendern.
     */
    private function hasWorkInProgress(CarbonImmutable $date): bool
    {
        $topicIds = TopicCandidate::query()
            ->whereDate('selected_for_date', $date)
            ->whereIn('status', [TopicStatus::SELECTED->value, TopicStatus::RESERVE->value])
            ->pluck('id');

        if ($topicIds->isEmpty()) {
            return false;
        }

        return ArticleDraft::query()
            ->whereIn('topic_candidate_id', $topicIds)
            ->whereNotIn('status', [DraftStatus::FAILED->value])
            ->exists();
    }

    /**
     * Bewertete Kandidaten, die noch zu haben sind: ueber der Mindestpunktzahl
     * und ausserhalb der Sperrfrist ihres Clusters.
     *
     * @return \Illuminate\Support\Collection<int, TopicCandidate>
     */
    private function pool(CarbonImmutable $date): \Illuminate\Support\Collection
    {
        $minScore = (float) config('content.topics.selection.min_total_score', 20.0);
        $cooldown = $date->subDays(max(0, (int) config('content.topics.selection.cooldown_days', 90)));

        $recentClusters = TopicCandidate::query()
            ->whereIn('status', [TopicStatus::SELECTED->value])
            ->whereNotNull('cluster_id')
            ->whereDate('selected_for_date', '>=', $cooldown)
            ->pluck('cluster_id')
            ->unique()
            ->all();

        return TopicCandidate::query()
            ->where('status', TopicStatus::SCORED->value)
            ->where('total_score', '>=', $minScore)
            ->when($recentClusters !== [], fn ($query) => $query->where(function ($inner) use ($recentClusters) {
                $inner->whereNull('cluster_id')->orWhereNotIn('cluster_id', $recentClusters);
            }))
            ->orderByDesc('total_score')
            ->get();
    }

    /**
     * Gierige Auswahl mit Diversitaetsstrafe.
     *
     * Der Auswahlwert ist der Gesamtscore, abzueglich eines Anteils fuer jedes
     * bereits gewaehlte Thema desselben Clusters bzw. derselben Region. Die
     * Strafe ist bewusst multiplikativ: ein deutlich besseres Thema setzt sich
     * trotz gleicher Region durch, ein gleich gutes nicht.
     *
     * @param  \Illuminate\Support\Collection<int, TopicCandidate>  $pool
     * @param  \Illuminate\Support\Collection<int, TopicCandidate>  $taken
     * @return array<int, TopicCandidate>
     */
    private function pick(\Illuminate\Support\Collection $pool, \Illuminate\Support\Collection $taken, int $count): array
    {
        if ($count <= 0) {
            return [];
        }

        $clusterPenalty = (float) config('content.topics.selection.cluster_penalty', 0.5);
        $regionPenalty = (float) config('content.topics.selection.region_penalty', 0.25);

        $chosen = [];
        $usedIds = $taken->pluck('id')->all();

        for ($round = 0; $round < $count; $round++) {
            $best = null;
            $bestValue = 0.0;

            foreach ($pool as $topic) {
                if (in_array($topic->getKey(), $usedIds, true)) {
                    continue;
                }

                $value = (float) $topic->total_score;

                foreach ($taken as $other) {
                    if ($topic->cluster_id !== null && $topic->cluster_id === $other->cluster_id) {
                        $value *= (1.0 - $clusterPenalty);
                    }

                    if ($topic->region_code !== null && $topic->region_code === $other->region_code) {
                        $value *= (1.0 - $regionPenalty);
                    }
                }

                if ($value > $bestValue) {
                    $best = $topic;
                    $bestValue = $value;
                }
            }

            if ($best === null) {
                break;
            }

            $chosen[] = $best;
            $usedIds[] = $best->getKey();
            $taken = $taken->concat([$best]);
        }

        return $chosen;
    }
}
