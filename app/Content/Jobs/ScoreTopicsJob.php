<?php

declare(strict_types=1);

namespace App\Content\Jobs;

use App\Content\Enums\TopicStatus;
use App\Content\Llm\Exceptions\BudgetExceededException;
use App\Content\Llm\LlmContext;
use App\Content\Models\TenantContentSetting;
use App\Content\Models\TopicCandidate;
use App\Content\Services\DuplicateChecker;
use App\Content\Services\KeywordClusterer;
use App\Content\Services\NavigationalTopicDetector;
use App\Content\Services\TopicScorer;
use App\Content\Support\BranchResolver;
use App\Models\Tenant;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Bewertet die offenen Themenkandidaten eines Mandanten (#12).
 *
 * Reihenfolge ist hier Absicht:
 *
 *   1. Ein Voyage-Aufruf fuer alle Kandidaten des Laufs (Batch, nicht je
 *      Kandidat) liefert Embedding und SimHash.
 *   2. Duplikate fliegen raus, bevor sie bewertet werden — ein Thema, das es
 *      im Netz schon gibt, braucht keine Teilscores.
 *   3. Danach die Kannibalisierungspruefung gegen die eigenen
 *      Search-Console-Zahlen.
 *   4. Was uebrig bleibt, wird bewertet und einem Keyword-Cluster zugeordnet.
 *      Das Clustern kommt nach dem Scoring, weil der staerkste Kandidat das
 *      Cluster praegen soll.
 *
 * Faellt Voyage aus oder ist das Budget erschoepft, wird nicht abgebrochen:
 * die Kandidaten werden ohne Aehnlichkeitsvergleich bewertet
 * (uniqueness_score = 100) und im Log vermerkt. Ein ausgefallener
 * Embedding-Anbieter darf die Tageskette nicht anhalten; die Duplikatspruefung
 * greift beim naechsten Lauf erneut, und das Qualitaetsgate (#15) prueft
 * ohnehin ein zweites Mal.
 */
class ScoreTopicsJob implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public int $timeout = 600;

    public function __construct(
        public int $tenantId,
        public bool $chainSelection = true,
    ) {
        $this->onQueue((string) config('content.pipeline.queues.discovery', 'content-discovery'));
    }

    public function uniqueId(): string
    {
        return 'content-score-topics:'.$this->tenantId;
    }

    public function uniqueFor(): int
    {
        return 3600;
    }

    public function handle(
        DuplicateChecker $duplicates,
        KeywordClusterer $clusterer,
        NavigationalTopicDetector $navigational,
    ): void {
        $tenant = Tenant::query()->find($this->tenantId);

        if ($tenant === null) {
            return;
        }

        $branch = BranchResolver::resolve($tenant);

        $tenant->run(function () use ($duplicates, $clusterer, $navigational, $branch): void {
            /** @var \Illuminate\Database\Eloquent\Collection<int, TopicCandidate> $topics */
            $topics = TopicCandidate::query()
                ->whereIn('status', [TopicStatus::DISCOVERED->value, TopicStatus::SCORED->value])
                ->orderByDesc('id')
                ->get();

            if ($topics->isEmpty()) {
                Log::info('Themen-Scoring uebersprungen: keine offenen Kandidaten.', [
                    'tenant_id' => $this->tenantId,
                ]);

                return;
            }

            $embeddings = $this->embed($duplicates, $topics);
            $scorer = new TopicScorer(TenantContentSetting::current());

            $rejected = ['navigational' => 0, 'duplicate' => 0, 'cannibalization' => 0];
            $entries = [];

            foreach ($topics as $index => $topic) {
                // Betriebssuchen ("elektriker hamburg") bedienen Stadt- und
                // Kategorieseiten, nicht der Ratgeber (#41).
                $navigationalReason = $navigational->reason($topic);

                if ($navigationalReason !== null) {
                    $this->reject($topic, NavigationalTopicDetector::REASON, $navigationalReason);

                    $rejected['navigational']++;

                    continue;
                }

                $embedding = $embeddings[$index] ?? [];
                $simhash = DuplicateChecker::simhash($duplicates->textFor($topic));

                $match = $embedding === []
                    ? ['reason' => null, 'similarity' => 0.0, 'scope' => null, 'match' => null]
                    : $duplicates->check($embedding, $simhash, $this->tenantId, $branch);

                $topic->forceFill([
                    'simhash' => $simhash,
                    'embedding_json' => $embedding === [] ? null : $embedding,
                ]);

                if ($match['reason'] === DuplicateChecker::REASON_DUPLICATE) {
                    $this->reject($topic, DuplicateChecker::REASON_DUPLICATE, sprintf(
                        'Aehnlichkeit %.2f zu "%s" (%s).',
                        $match['similarity'],
                        (string) $match['match'],
                        $match['scope'] === 'tenant' ? 'eigener Ratgeber' : 'Portalnetz',
                    ));

                    $rejected['duplicate']++;

                    continue;
                }

                $cannibal = $duplicates->cannibalization($topic);

                if ($cannibal !== null) {
                    $this->reject($topic, DuplicateChecker::REASON_CANNIBALIZATION, sprintf(
                        'Eigene Seite %s rankt zu "%s" bereits auf Position %.1f.',
                        $cannibal['page'],
                        $cannibal['query'],
                        $cannibal['position'],
                    ));

                    $rejected['cannibalization']++;

                    continue;
                }

                $scorer->score($topic, ['similarity' => $match['similarity']]);

                if ($topic->status === TopicStatus::DISCOVERED) {
                    $topic->forceFill(['status' => TopicStatus::SCORED]);
                }

                $topic->save();

                if ($embedding !== []) {
                    $entries[] = ['topic' => $topic, 'embedding' => $embedding];
                }
            }

            if ($entries !== []) {
                $clusterer->assign($entries);

                foreach ($entries as $entry) {
                    $entry['topic']->save();
                }
            }

            Log::info('Themen-Scoring abgeschlossen.', [
                'tenant_id' => $this->tenantId,
                'scored' => count($entries),
                'rejected' => $rejected,
            ]);
        });

        if ($this->chainSelection) {
            SelectDailyTopicsJob::dispatch($this->tenantId);
        }
    }

    /**
     * Ein Voyage-Aufruf fuer alle Kandidaten. Faellt er aus, laeuft der Job
     * ohne Aehnlichkeitsvergleich weiter.
     *
     * @param  \Illuminate\Database\Eloquent\Collection<int, TopicCandidate>  $topics
     * @return array<int, array<int, float>>
     */
    private function embed(DuplicateChecker $duplicates, $topics): array
    {
        $texts = $topics->map(fn (TopicCandidate $topic): string => $duplicates->textFor($topic))->all();

        try {
            return $duplicates->embedMany($texts, new LlmContext(
                tenantId: $this->tenantId,
                operation: 'topic_embedding',
            ));
        } catch (BudgetExceededException $exception) {
            Log::warning('Themen-Scoring ohne Embeddings: Budget erschoepft.', [
                'tenant_id' => $this->tenantId,
                'exception' => $exception->getMessage(),
            ]);
        } catch (\Throwable $exception) {
            Log::error('Themen-Scoring ohne Embeddings: Anbieter nicht erreichbar.', [
                'tenant_id' => $this->tenantId,
                'exception' => $exception->getMessage(),
            ]);
        }

        return [];
    }

    private function reject(TopicCandidate $topic, string $reason, string $detail): void
    {
        $topic->forceFill([
            'status' => TopicStatus::REJECTED,
            'rejection_reason' => $reason,
            'rationale' => trim((string) $topic->rationale.' '.$detail),
        ])->save();
    }
}
