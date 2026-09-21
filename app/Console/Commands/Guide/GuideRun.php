<?php

declare(strict_types=1);

namespace App\Console\Commands\Guide;

use App\Guide\Jobs\TopicChainFactory;
use App\Guide\Models\Topic;
use App\Guide\Models\TopicRun;
use App\Guide\Services\TopicRunStarter;
use App\Models\Tenant;
use Illuminate\Console\Command;

/**
 * Stoesst den Lauf einzelner Themen ausserhalb des Tageslaufs an (#15).
 * Das Dashboard ruft ihn fuer "Jetzt ausfuehren" auf.
 *
 *   php artisan guide:run --tenant=7 --topic=12 --topic=13
 *   php artisan guide:run --tenant=sanitaerfinder.com --topic=12 --json
 *   php artisan guide:run 12 --tenant=7 --force
 *
 * Legt je Thema den Lauf von heute an (Status queued) und meldet ihn ueber
 * RunRequested an die Job-Kette; hoechstens ein Lauf je Thema und Tag.
 *
 * --force (#13) fuehrt die ganze Kette sofort in diesem Prozess aus
 * (Queue-Verbindung sync, ohne Laufzeitfenster und Parallelitaetsgrenze)
 * und setzt einen heute schon begonnenen, noch nicht beendeten Lauf fort,
 * statt ihn nur zu melden. Ein heute schon beendeter Lauf bleibt beendet.
 */
class GuideRun extends Command
{
    protected $signature = 'guide:run
        {topic? : ID oder Slug des Themas (alternativ --topic)}
        {--tenant= : Portal (ID, UUID oder Domain)}
        {--topic=* : ID des Themas in diesem Portal, mehrfach möglich}
        {--force : Kette sofort komplett in diesem Prozess ausführen, laufenden Lauf fortsetzen}
        {--json : Ergebnis als JSON ausgeben (für das Dashboard)}';

    protected $description = 'Startet den Lauf einzelner Ratgeber-Themen ausserhalb des Tageslaufs';

    public function handle(TopicRunStarter $starter, TopicChainFactory $chains): int
    {
        $tenant = $this->tenant(trim((string) $this->option('tenant')));

        if ($tenant === null) {
            $this->error('Portal nicht gefunden; --tenant mit ID, UUID oder Domain angeben.');

            return self::FAILURE;
        }

        $needles = array_values(array_unique(array_filter([
            trim((string) $this->argument('topic')),
            ...array_map(static fn ($id): string => trim((string) $id), (array) $this->option('topic')),
        ], static fn (string $needle): bool => $needle !== '')));

        if ($needles === []) {
            $this->error('Mindestens ein Thema angeben (Argument oder --topic).');

            return self::FAILURE;
        }

        $force = (bool) $this->option('force');

        if ($force) {
            // Jeder Job und jeder Listener der Kette laeuft damit sofort im
            // selben Prozess; LimitGuideConcurrency laesst synchrone Jobs durch.
            config(['queue.default' => 'sync']);
        }

        /** @var array<int, array{topic: int|string, outcome: string, run_id: int|null, reason: string|null}> $results */
        $results = $tenant->run(function () use ($needles, $starter, $chains, $tenant, $force): array {
            $results = [];

            foreach ($needles as $needle) {
                $topic = ctype_digit($needle)
                    ? Topic::query()->find((int) $needle)
                    : Topic::query()->where('slug', $needle)->first();

                if ($topic === null) {
                    $results[] = ['topic' => $needle, 'outcome' => TopicRunStarter::NOT_RUNNABLE, 'run_id' => null, 'reason' => __('Thema nicht gefunden.')];

                    continue;
                }

                $result = $starter->start($topic, $tenant->getKey());

                if ($force && $result['outcome'] === TopicRunStarter::ALREADY_RUNNING && $result['run_id'] !== null) {
                    $run = TopicRun::query()->find($result['run_id']);

                    if ($run !== null && $chains->dispatch((int) $tenant->getKey(), $run)) {
                        $result = ['outcome' => TopicRunStarter::STARTED, 'run_id' => $result['run_id'], 'reason' => __('Bestehender Lauf von heute fortgesetzt.')];
                    }
                }

                // Nach dem synchronen Durchlauf steht fest, wo der Lauf endete.
                if ($force && $result['run_id'] !== null) {
                    $status = TopicRun::query()->find($result['run_id'])?->status;
                    $result['reason'] = trim(($result['reason'] ?? '').' '.__('Status: :status', ['status' => $status?->label() ?? '–']));
                }

                $results[] = ['topic' => (int) $topic->getKey(), ...$result];
            }

            return $results;
        });

        if ($this->option('json')) {
            $this->line((string) json_encode($results, JSON_UNESCAPED_UNICODE));

            return self::SUCCESS;
        }

        $this->table(['Thema', 'Ergebnis', 'Lauf', 'Hinweis'], array_map(
            static fn (array $result): array => [$result['topic'], $result['outcome'], $result['run_id'] ?? '–', $result['reason'] ?? ''],
            $results,
        ));

        return self::SUCCESS;
    }

    private function tenant(string $needle): ?Tenant
    {
        if ($needle === '') {
            return null;
        }

        if (ctype_digit($needle)) {
            return Tenant::query()->find((int) $needle);
        }

        return Tenant::query()
            ->where('uuid', $needle)
            ->orWhere('domain', $needle)
            ->first();
    }
}
