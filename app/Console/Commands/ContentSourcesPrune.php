<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Content\Models\DraftSource;
use App\Content\Models\FactSnippet;
use App\Content\Models\SourceItem;
use App\Content\Models\TopicCandidate;
use App\Models\Tenant;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Collection;
use Stancl\Tenancy\Database\TenantCollection;

/**
 * Raeumt alte Rohsignale ab (#7).
 *
 * Geloescht wird nur, was aelter als die Aufbewahrungsfrist ist und an keinem
 * Themenkandidaten, Entwurf oder Faktenschnipsel haengt — belegte Quellen
 * muessen fuer den Faktencheck (#15) und die Quellenangabe (#27) erhalten
 * bleiben.
 */
class ContentSourcesPrune extends Command
{
    protected $signature = 'content:sources:prune
        {--days= : Aufbewahrungsfrist in Tagen, sonst content.sources.retention_days}
        {--tenant= : Mandant (ID, UUID oder Domain), sonst alle}
        {--dry-run : Nur zaehlen, nichts loeschen}';

    protected $description = 'Loescht source_items aelter als die Aufbewahrungsfrist, sofern sie unverknuepft sind';

    private const CHUNK_SIZE = 500;

    public function handle(): int
    {
        $days = (int) ($this->option('days') ?? config('content.sources.retention_days', 30));
        $cutoff = now()->subDays($days);
        $dryRun = (bool) $this->option('dry-run');

        $tenants = $this->tenants();

        if ($tenants->isEmpty()) {
            $this->warn('Keine Mandanten gefunden.');

            return self::FAILURE;
        }

        $total = 0;

        /** @var Tenant $tenant */
        foreach ($tenants as $tenant) {
            $deleted = $tenant->run(fn () => $this->pruneTenant($cutoff, $dryRun));
            $total += $deleted;

            $this->line("[{$tenant->name}] {$deleted} Rohsignale".($dryRun ? ' loeschbar' : ' geloescht'));
        }

        $this->info(($dryRun ? 'Vorschau: ' : '')."{$total} Rohsignale aelter als {$days} Tage.");

        return self::SUCCESS;
    }

    /**
     * Laeuft im Tenant-Kontext.
     */
    private function pruneTenant(\DateTimeInterface $cutoff, bool $dryRun): int
    {
        $protected = $this->protectedIds();
        $deleted = 0;

        SourceItem::query()
            ->where('fetched_at', '<', $cutoff)
            ->select('id')
            ->chunkById(self::CHUNK_SIZE, function (Collection $items) use (&$deleted, $dryRun, $protected): void {
                // Die geschuetzten IDs werden hier gefiltert statt per
                // whereNotIn: die Liste kann ueber alle Themen hinweg lang
                // werden und gehoert nicht in jede Abfrage.
                $ids = array_values(array_diff($items->modelKeys(), $protected));

                if ($ids === []) {
                    return;
                }

                if (! $dryRun) {
                    SourceItem::query()->whereIn('id', $ids)->delete();
                }

                $deleted += count($ids);
            });

        return $deleted;
    }

    /**
     * IDs, die an einem Thema, einem Entwurf oder einem Fakt haengen.
     *
     * Die Themenverknuepfung liegt als JSON-Liste
     * (topic_candidates.source_item_ids_json) und wird deshalb in PHP
     * aufgeloest; die Menge ist durch die Aufbewahrungsfrist begrenzt.
     *
     * @return array<int, int>
     */
    private function protectedIds(): array
    {
        $ids = [];

        TopicCandidate::query()
            ->whereNotNull('source_item_ids_json')
            ->select(['id', 'source_item_ids_json'])
            ->chunkById(self::CHUNK_SIZE, function (Collection $topics) use (&$ids): void {
                foreach ($topics as $topic) {
                    foreach ($topic->source_item_ids_json ?? [] as $id) {
                        $ids[(int) $id] = true;
                    }
                }
            });

        foreach (DraftSource::query()->whereNotNull('source_item_id')->pluck('source_item_id') as $id) {
            $ids[(int) $id] = true;
        }

        foreach (FactSnippet::query()->whereNotNull('source_item_id')->pluck('source_item_id') as $id) {
            $ids[(int) $id] = true;
        }

        return array_keys($ids);
    }

    private function tenants(): TenantCollection
    {
        $tenant = $this->option('tenant');

        if ($tenant === null) {
            return Tenant::all();
        }

        if (is_numeric($tenant)) {
            return Tenant::query()->where('id', (int) $tenant)->get();
        }

        return Tenant::query()
            ->where('uuid', $tenant)
            ->orWhere('domain', $tenant)
            ->get();
    }
}
