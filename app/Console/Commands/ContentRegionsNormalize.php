<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Content\Sources\Support\StateCatalog;
use App\Models\Tenant;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Stancl\Tenancy\Database\TenantCollection;
use Throwable;

/**
 * Zieht Bestandsdaten auf das ISO-3166-2-Vokabular nach (#33).
 *
 * Der GSC-Connector hat Bundeslaender bis #33 als Slug geschrieben
 * ('bayern'), der Trends-Connector als ISO-Code ('DE-BY'). Damit standen zwei
 * Codes fuer dasselbe Land in derselben Spalte und die Gruppierung in Scoring
 * und Duplikatspruefung (#12) hat die Signale nicht zusammengefuehrt.
 *
 * Entschieden wurde Umschreiben statt Verwerfen: die Zuordnung Slug -> ISO ist
 * eindeutig, und die Rohsignale haengen ueber source_item_id an Themen,
 * Entwuerfen und Fakten. Ein Loeschen wuerde diese Belege mitnehmen.
 *
 * Nur Zeilen mit region_scope = 'state' werden angefasst; Staedte behalten
 * ihren Slug.
 */
class ContentRegionsNormalize extends Command
{
    protected $signature = 'content:regions:normalize
        {--tenant= : Mandant (ID, UUID oder Domain), sonst alle}
        {--dry-run : Nur zaehlen, nichts schreiben}';

    protected $description = 'Schreibt Bundesland-Slugs in region_code auf ISO-3166-2-Codes um';

    /**
     * Tabellen mit dem Paar region_scope/region_code.
     *
     * @var array<int, string>
     */
    private const TABLES = [
        'source_items',
        'keyword_clusters',
        'topic_candidates',
        'article_drafts',
        'fact_snippets',
        'seasonal_topics',
    ];

    public function handle(): int
    {
        $tenants = $this->tenants();

        if ($tenants->isEmpty()) {
            $this->warn('Keine Mandanten gefunden.');

            return self::FAILURE;
        }

        $dryRun = (bool) $this->option('dry-run');
        $map = $this->slugToIso();
        $total = 0;

        /** @var Tenant $tenant */
        foreach ($tenants as $tenant) {
            try {
                // Kein Transaktionsklammern ueber den Tenant-Lauf: tenancy
                // verwirft die Verbindung beim Wechsel, offene Transaktionen
                // gingen verloren.
                $changed = $tenant->run(fn (): int => $this->normalizeTenant($map, $dryRun));
            } catch (Throwable $e) {
                // Tenant-DBs ohne Content-Pipeline sind zulaessig.
                $this->warn("[{$tenant->name}] uebersprungen: {$e->getMessage()}");

                continue;
            }

            $total += $changed;

            $this->line("[{$tenant->name}] {$changed} Zeilen".($dryRun ? ' umzuschreiben' : ' umgeschrieben'));
        }

        $this->info(($dryRun ? 'Vorschau: ' : '')."{$total} Zeilen auf ISO-3166-2 gebracht.");

        return self::SUCCESS;
    }

    /**
     * Laeuft im Tenant-Kontext.
     *
     * @param  array<string, string>  $map
     */
    private function normalizeTenant(array $map, bool $dryRun): int
    {
        $changed = 0;

        foreach (self::TABLES as $table) {
            if (! Schema::hasTable($table)) {
                continue;
            }

            foreach ($map as $slug => $iso) {
                $query = DB::table($table)
                    ->where('region_scope', 'state')
                    ->where('region_code', $slug);

                $changed += $dryRun ? $query->count() : $query->update(['region_code' => $iso]);
            }
        }

        return $changed + $this->normalizeSettings($map, $dryRun);
    }

    /**
     * preferred_states_json darf ISO-Code, Name oder alten Slug enthalten;
     * gespeichert wird nur noch der ISO-Code.
     *
     * @param  array<string, string>  $map
     */
    private function normalizeSettings(array $map, bool $dryRun): int
    {
        if (! Schema::hasTable('tenant_content_settings')) {
            return 0;
        }

        $changed = 0;

        foreach (DB::table('tenant_content_settings')->select(['id', 'preferred_states_json'])->get() as $row) {
            $current = json_decode((string) ($row->preferred_states_json ?? ''), true);

            if (! is_array($current) || $current === []) {
                continue;
            }

            $codes = [];

            foreach ($current as $entry) {
                if (! is_string($entry) || trim($entry) === '') {
                    continue;
                }

                $value = trim($entry);
                $iso = StateCatalog::exists(mb_strtoupper($value))
                    ? mb_strtoupper($value)
                    : ($map[mb_strtolower($value)] ?? StateCatalog::fromText($value));

                if ($iso !== null) {
                    $codes[$iso] = true;
                }
            }

            $codes = array_keys($codes);

            if ($codes === array_values($current)) {
                continue;
            }

            $changed++;

            if (! $dryRun) {
                DB::table('tenant_content_settings')
                    ->where('id', $row->id)
                    ->update(['preferred_states_json' => json_encode($codes)]);
            }
        }

        return $changed;
    }

    /**
     * Alter Slug ('baden-wuerttemberg') => ISO-Code ('DE-BW').
     *
     * @return array<string, string>
     */
    private function slugToIso(): array
    {
        $map = [];

        foreach (StateCatalog::all() as $iso => $name) {
            $map[Str::slug($name)] = $iso;
            $map[mb_strtolower($name)] = $iso;
        }

        return $map;
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
