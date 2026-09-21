<?php

declare(strict_types=1);

namespace App\Console\Commands\Guide;

use App\Guide\Legacy\LegacyArticleMatcher;
use App\Guide\Legacy\LegacyCategorizer;
use App\Guide\Services\CategoryAdminService;
use App\Guide\Services\GuidePageData;
use App\Models\Portal\Post;
use App\Models\Tenant;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;

/**
 * Ordnet die Altartikel (Beitraege ohne Ratgeber-Thema) einer Ratgeber-
 * Kategorie zu (#19): ohne --smart "Allgemein", mit --smart die per LLM
 * passendste vorhandene Kategorie. Wiederholbar, bereits zugeordnete
 * Beitraege bleiben unberuehrt.
 *
 * Danach prueft der Befehl je Portal, dass jeder veroeffentlichte Altartikel
 * eine Kategorie hat und seine Adresse /ratgeber/{slug} nicht von einer
 * festen Ratgeber-Seite verdeckt wird — sonst Exit-Code 1.
 *
 *   php artisan guide:legacy:categorize --tenant='*'
 *   php artisan guide:legacy:categorize --tenant=sanitaerfinder.com --smart
 */
class GuideLegacyCategorize extends Command
{
    protected $signature = 'guide:legacy:categorize
        {--tenant=* : Portale (ID, UUID oder Domain); "*" fuer alle}
        {--smart : passendste vorhandene Kategorie per LLM statt "Allgemein"}';

    protected $description = 'Ordnet die Bestandsartikel des Ratgebers einer Kategorie zu';

    public function handle(LegacyCategorizer $categorizer): int
    {
        $tenants = $this->tenants();

        if ($tenants === null) {
            return self::FAILURE;
        }

        $smart = (bool) $this->option('smart');
        $rows = [];
        $failed = false;

        foreach ($tenants as $tenant) {
            $row = $tenant->run(function () use ($tenant, $categorizer, $smart): ?array {
                // Je Portal neu: GuidePageData merkt sich das Ergebnis.
                if (! app(GuidePageData::class)->available()) {
                    $this->warn("[{$tenant->name}] Ratgeber-Tabellen fehlen (tenants:migrate), übersprungen.");

                    return null;
                }

                $result = $categorizer->categorize($smart, fn (string $message) => $this->warn("[{$tenant->name}] {$message}"));
                $problems = $this->check();

                foreach ($problems as $problem) {
                    $this->error("[{$tenant->name}] {$problem}");
                }

                return [
                    (int) $tenant->getKey(),
                    (string) $tenant->name,
                    $result['assigned'],
                    $result['fallback'],
                    implode(', ', array_map(fn (string $name, int $count): string => "{$name}: {$count}", array_keys($result['by_category']), $result['by_category'])),
                    count($problems),
                ];
            });

            if ($row === null) {
                continue;
            }

            $failed = $failed || $row[5] > 0;
            $rows[] = $row;
        }

        $this->table(['ID', 'Portal', 'Zugeordnet', 'Davon Allgemein', 'Kategorien', 'Probleme'], $rows);

        return $failed ? self::FAILURE : self::SUCCESS;
    }

    /**
     * Veroeffentlichte Altartikel ohne Kategorie oder mit einem Slug, den eine
     * feste Seite unter /ratgeber/ verdeckt.
     *
     * @return list<string>
     */
    private function check(): array
    {
        $problems = [];

        $posts = LegacyArticleMatcher::legacyPosts()->published()->get(['id', 'slug', 'guide_category_id']);

        foreach ($posts as $post) {
            /** @var Post $post */
            if ($post->guide_category_id === null) {
                $problems[] = "Beitrag {$post->id} ({$post->slug}) hat keine Kategorie.";
            }

            if (in_array($post->slug, CategoryAdminService::RESERVED_SLUGS, true)) {
                $problems[] = "Beitrag {$post->id}: /ratgeber/{$post->slug} ist eine feste Seite, der Beitrag ist dort nicht erreichbar.";
            }
        }

        return $problems;
    }

    /**
     * @return Collection<int, Tenant>|null null bei fehlender oder unbekannter Angabe
     */
    private function tenants(): ?Collection
    {
        $needles = array_values(array_filter(array_map('trim', (array) $this->option('tenant'))));

        if ($needles === []) {
            $this->error('Mindestens ein --tenant angeben (ID, UUID, Domain oder "*" für alle).');

            return null;
        }

        if (in_array('*', $needles, true)) {
            /** @var Collection<int, Tenant> $all */
            $all = Tenant::query()->orderBy('id')->get()->toBase();

            return $all;
        }

        $tenants = collect();

        foreach ($needles as $needle) {
            $tenant = $this->resolve($needle);

            if ($tenant === null) {
                $this->error("Portal nicht gefunden: {$needle}");

                return null;
            }

            $tenants->put($tenant->getKey(), $tenant);
        }

        return $tenants->values();
    }

    private function resolve(string $needle): ?Tenant
    {
        if (ctype_digit($needle)) {
            return Tenant::query()->find((int) $needle);
        }

        return Tenant::query()
            ->where('uuid', $needle)
            ->orWhere('domain', $needle)
            ->first();
    }
}
