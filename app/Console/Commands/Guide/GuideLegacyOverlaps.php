<?php

declare(strict_types=1);

namespace App\Console\Commands\Guide;

use App\Guide\Legacy\LegacyArticleMatcher;
use App\Guide\Legacy\LegacyOverlapResolver;
use App\Guide\Models\Central\GuideAlert;
use App\Guide\Services\GuidePageData;
use App\Models\Tenant;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;

/**
 * Meldet Paare (Thema, Altartikel) mit aehnlichem Titel (#19): als Tabelle
 * und je Paar als Alarm `legacy_overlap` in guide_alerts, damit im Dashboard
 * entschieden werden kann — Slug-Uebernahme (nur ohne veroeffentlichten
 * Artikel des Themas) oder 301 vom Altartikel auf das Thema
 * (LegacyOverlapResolver). Wiederholbar; der Alarm eines Paares wird
 * ueber den dedupe_key fortgeschrieben, nicht doppelt angelegt.
 *
 * Paare, die im Dashboard als "Keine Ueberschneidung" vermerkt sind
 * (decision = dismissed), werden nicht wieder geoeffnet
 * (design/guide-dashboard.md §5.6.6). Irrtum: --reopen-dismissed hebt den
 * Vermerk auf, der naechste Abgleich meldet das Paar dann erneut.
 *
 *   php artisan guide:legacy:overlaps --tenant=sanitaerfinder.com
 *   php artisan guide:legacy:overlaps --tenant='*' --threshold=80
 *   php artisan guide:legacy:overlaps --reopen-dismissed=7:123:456
 */
class GuideLegacyOverlaps extends Command
{
    protected $signature = 'guide:legacy:overlaps
        {--tenant=* : Portale (ID, UUID oder Domain); "*" fuer alle}
        {--threshold= : Mindestaehnlichkeit der Titel 0..100 (Vorgabe 60)}
        {--reopen-dismissed=* : "Keine Ueberschneidung" aufheben, je Paar tenant-id:thema-id:beitrag-id}';

    protected $description = 'Listet Ratgeber-Themen, die einen Bestandsartikel abdecken, und meldet sie im Dashboard';

    public function handle(LegacyArticleMatcher $matcher): int
    {
        if ($this->option('reopen-dismissed') !== []) {
            return $this->reopenDismissed();
        }

        $tenants = $this->tenants();

        if ($tenants === null) {
            return self::FAILURE;
        }

        $threshold = $this->option('threshold') !== null
            ? (float) $this->option('threshold')
            : LegacyArticleMatcher::DEFAULT_THRESHOLD;

        if ($threshold <= 0 || $threshold > 100) {
            $this->error('--threshold muss zwischen 0 und 100 liegen.');

            return self::FAILURE;
        }

        $rows = [];
        $skipped = 0;

        foreach ($tenants as $tenant) {
            $dismissed = $this->dismissedKeys((int) $tenant->getKey());

            $tenantRows = $tenant->run(function () use ($tenant, $matcher, $threshold, $dismissed, &$skipped): array {
                // Je Portal neu: GuidePageData merkt sich das Ergebnis.
                if (! app(GuidePageData::class)->available()) {
                    $this->warn("[{$tenant->name}] Ratgeber-Tabellen fehlen (tenants:migrate), übersprungen.");

                    return [];
                }

                $rows = [];

                foreach ($matcher->overlaps($threshold) as $pair) {
                    $topic = $pair['topic'];
                    $post = $pair['post'];
                    $dedupeKey = LegacyOverlapResolver::dedupeKey((int) $tenant->getKey(), (int) $topic->id, (int) $post->id);

                    if (isset($dismissed[$dedupeKey])) {
                        $skipped++;

                        continue;
                    }

                    $published = LegacyOverlapResolver::publishedArticle($topic);
                    $postUrl = route('guide.show', $post->slug, false);
                    $question = GuidePageData::replaceYear($topic->question);

                    GuideAlert::raise(
                        $dedupeKey,
                        GuideAlert::KEY_LEGACY_OVERLAP,
                        GuideAlert::LEVEL_INFO,
                        "Thema „{$question}“ deckt den Altartikel {$postUrl} ab ({$pair['similarity']} % Titelähnlichkeit).",
                        [
                            'tenant_id' => (int) $tenant->getKey(),
                            'guide_topic_id' => (int) $topic->id,
                            'context_json' => [
                                'topic_id' => (int) $topic->id,
                                'topic_question' => $question,
                                'topic_slug' => (string) $topic->slug,
                                'post_id' => (int) $post->id,
                                'post_title' => (string) $post->title,
                                'post_slug' => (string) $post->slug,
                                'post_url' => $postUrl,
                                'similarity' => $pair['similarity'],
                                // Slug-Uebernahme nur ohne veroeffentlichten Artikel, 301 nur mit.
                                'can_adopt_slug' => $published === null,
                                'can_redirect' => $published !== null,
                                'target_url' => $published !== null ? route('guide.show', $published->slug, false) : null,
                            ],
                        ],
                    );

                    $rows[] = [
                        (string) $tenant->name,
                        (int) $topic->id,
                        $question,
                        (int) $post->id,
                        $postUrl,
                        number_format($pair['similarity'], 1, ',', '').' %',
                        $published === null ? 'Slug-Übernahme' : '301',
                    ];
                }

                return $rows;
            });

            array_push($rows, ...$tenantRows);
        }

        if ($skipped > 0) {
            $this->line("{$skipped} Paare sind als keine Überschneidung vermerkt und werden nicht erneut gemeldet.");
        }

        if ($rows === []) {
            $this->info('Keine Überschneidungen gefunden.');

            return self::SUCCESS;
        }

        $this->table(['Portal', 'Thema', 'Frage', 'Beitrag', 'Altartikel', 'Ähnlichkeit', 'Möglich'], $rows);
        $this->info(count($rows).' Paare in guide_alerts gemeldet.');

        return self::SUCCESS;
    }

    /**
     * dedupe_keys der Paare mit "Keine Ueberschneidung" in diesem Portal.
     * Gefiltert in PHP, nicht per JSON-Pfad in SQL.
     *
     * @return array<string, bool>
     */
    private function dismissedKeys(int $tenantId): array
    {
        return GuideAlert::query()
            ->where('key', GuideAlert::KEY_LEGACY_OVERLAP)
            ->where('tenant_id', $tenantId)
            ->where('status', GuideAlert::STATUS_RESOLVED)
            ->get(['id', 'dedupe_key', 'status', 'context_json'])
            ->filter(fn (GuideAlert $alert): bool => LegacyOverlapResolver::isDismissed($alert))
            ->mapWithKeys(fn (GuideAlert $alert): array => [(string) $alert->dedupe_key => true])
            ->all();
    }

    private function reopenDismissed(): int
    {
        $resolver = app(LegacyOverlapResolver::class);
        $failed = false;

        foreach ((array) $this->option('reopen-dismissed') as $pair) {
            if (! preg_match('/^(\d+):(\d+):(\d+)$/', trim((string) $pair), $match)) {
                $this->error("Ungültige Angabe: {$pair} (erwartet tenant-id:thema-id:beitrag-id)");
                $failed = true;

                continue;
            }

            if (! $resolver->reopenDismissed((int) $match[1], (int) $match[2], (int) $match[3])) {
                $this->warn("{$pair}: nicht als keine Überschneidung vermerkt.");

                continue;
            }

            $this->info("{$pair}: wird beim nächsten Abgleich wieder gemeldet.");
        }

        return $failed ? self::FAILURE : self::SUCCESS;
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
