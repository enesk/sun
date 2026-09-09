<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Content\Assets\AssetStorage;
use App\Content\Enums\DraftStatus;
use App\Content\Jobs\GenerateAssetsJob;
use App\Content\Models\ArticleDraft;
use App\Models\Tenant;
use Illuminate\Console\Command;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Stancl\Tenancy\Database\TenantCollection;

/**
 * Titelbild und Infografik erzeugen und die Ergebnisse zeigen (#16).
 *
 * Ohne --draft nimmt der Befehl die freigegebenen Entwuerfe, die noch kein
 * Titelbild haben. Mit --sync laeuft die Erzeugung im Vordergrund und der
 * Befehl zeigt danach Herkunft, Dateigroessen und die oeffentlichen URLs —
 * das ist der Weg fuer die Staging-Abnahme.
 */
class ContentAssetsGenerate extends Command
{
    protected $signature = 'content:assets
        {--tenant= : Mandant (ID, UUID oder Domain), sonst alle}
        {--draft= : Nur diesen Entwurf (setzt --tenant voraus)}
        {--limit= : Hoechstens so viele Entwuerfe je Mandant}
        {--force : Auch Entwuerfe, die schon ein Titelbild haben}
        {--sync : Sofort ausfuehren statt in die Queue zu stellen}';

    protected $description = 'Erzeugt Titelbild und Infografik freigegebener Artikelentwuerfe';

    public function handle(AssetStorage $storage): int
    {
        if (! config('content.enabled')) {
            $this->warn('Die Content-Pipeline ist deaktiviert (content.enabled).');

            return self::SUCCESS;
        }

        $tenants = $this->tenants();

        if ($tenants->isEmpty()) {
            $this->error('Keine Mandanten gefunden.');

            return self::FAILURE;
        }

        $draftOption = $this->option('draft');

        if ($draftOption !== null && $tenants->count() !== 1) {
            $this->error('--draft braucht genau einen Mandanten (--tenant).');

            return self::FAILURE;
        }

        $sync = (bool) $this->option('sync');

        /** @var Tenant $tenant */
        foreach ($tenants as $tenant) {
            $drafts = $this->drafts($tenant, $draftOption === null ? null : (int) $draftOption);

            if ($drafts === []) {
                $this->warn("[{$tenant->name}] kein Entwurf ohne Assets.");

                continue;
            }

            foreach ($drafts as $draftId) {
                $sync
                    ? GenerateAssetsJob::dispatchSync((int) $tenant->getKey(), $draftId)
                    : GenerateAssetsJob::dispatch((int) $tenant->getKey(), $draftId);
            }

            $this->line("[{$tenant->name}] ".count($drafts).($sync ? ' bearbeitet.' : ' eingereiht.'));

            if ($sync) {
                $this->report($tenant, $drafts, $storage);
            }
        }

        return self::SUCCESS;
    }

    /**
     * @return array<int, int>
     */
    private function drafts(Tenant $tenant, ?int $draftId): array
    {
        return $tenant->run(function () use ($draftId): array {
            if ($draftId !== null) {
                return ArticleDraft::query()->whereKey($draftId)->exists() ? [$draftId] : [];
            }

            $limit = $this->option('limit') !== null ? max(1, (int) $this->option('limit')) : 25;

            return ArticleDraft::query()
                ->withStatus(DraftStatus::APPROVED)
                ->when(! $this->option('force'), fn ($query) => $query->whereNull('hero_image_path'))
                ->orderBy('id')
                ->limit($limit)
                ->pluck('id')
                ->map('intval')
                ->all();
        });
    }

    /**
     * @param  array<int, int>  $draftIds
     */
    private function report(Tenant $tenant, array $draftIds, AssetStorage $storage): void
    {
        $tenant->run(function () use ($draftIds, $storage): void {
            $drafts = ArticleDraft::query()->whereIn('id', $draftIds)->orderBy('id')->get();

            $this->table(
                ['Entwurf', 'Quelle', 'Hero (KB)', 'Varianten', 'Infografik', 'Alt-Text'],
                $drafts->map(function (ArticleDraft $draft): array {
                    $assets = (array) ($draft->assets_json ?? []);
                    $variants = (array) Arr::get($assets, 'hero.variants', []);
                    $bytes = (int) ($variants[0]['bytes'] ?? 0);

                    return [
                        Str::limit((string) $draft->title, 30),
                        (string) ($draft->hero_image_source ?? '-'),
                        $bytes > 0 ? (string) round($bytes / 1024, 1) : '-',
                        implode('/', array_map(static fn (array $v): string => (string) $v['width'], $variants)) ?: '-',
                        $draft->infographic_svg_path !== null ? 'ja' : 'nein',
                        Str::limit((string) ($draft->hero_image_alt ?? '-'), 40),
                    ];
                })->all(),
            );

            foreach ($drafts as $draft) {
                if ($draft->hero_image_path !== null) {
                    $this->line('  '.$storage->url($draft->hero_image_path));
                }

                if ($draft->infographic_svg_path !== null) {
                    $this->line('  '.$storage->url($draft->infographic_svg_path));
                }

                if ($draft->hero_image_credit !== null) {
                    $this->line('  Bildnachweis: '.$draft->hero_image_credit);
                }

                foreach ((array) Arr::get((array) ($draft->assets_json ?? []), 'errors', []) as $error) {
                    $this->error('  '.Str::limit((string) $error, 160));
                }
            }
        });
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
