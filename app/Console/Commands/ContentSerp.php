<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Content\Services\SerpInsightDto;
use App\Content\Services\SerpInsightService;
use App\Models\Tenant;
use Illuminate\Console\Command;
use InvalidArgumentException;
use Throwable;

/**
 * Diagnoseaufruf der SERP-Analyse (#10, Definition of Done).
 *
 * Zeigt in einem Durchlauf, dass Zugangsdaten, Kontingent, Zwischenspeicher
 * und der eigene Abruf der Wettbewerberseiten zusammenspielen:
 *
 *   php artisan content:serp "fliesenleger kosten"
 *   php artisan content:serp "fliesenleger kosten" --region=DE-BY --fresh
 */
class ContentSerp extends Command
{
    protected $signature = 'content:serp
        {keyword : Zielkeyword}
        {--region= : ISO-3166-2-Code eines Bundeslands (z. B. DE-BY), sonst bundesweit}
        {--tenant= : Mandant (ID, UUID oder Domain), sonst der erste}
        {--fresh : Zwischenspeicher uebergehen}
        {--json : Vollstaendiges DTO als JSON ausgeben}';

    protected $description = 'Fragt SERP, People Also Ask, Autocomplete und Suchvolumen zu einem Keyword ab';

    public function handle(SerpInsightService $service): int
    {
        $tenant = $this->tenant();

        if (! $tenant instanceof Tenant) {
            return self::FAILURE;
        }

        $keyword = (string) $this->argument('keyword');
        $region = $this->option('region');

        $this->line("Mandant: {$tenant->name}");
        $this->line('Region: '.($region === null ? 'Deutschland' : (string) $region));

        try {
            /** @var SerpInsightDto $insight */
            $insight = $tenant->run(fn () => $service->for($keyword, $region === null ? null : (string) $region, (bool) $this->option('fresh')));
        } catch (InvalidArgumentException $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        } catch (Throwable $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        if ($insight->isEmpty()) {
            $this->error('Kein Ergebnis — Zugangsdaten, Kontingent oder Budget pruefen.');

            return self::FAILURE;
        }

        if ($this->option('json')) {
            $this->line((string) json_encode($insight->toArray(), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

            return self::SUCCESS;
        }

        $this->show($insight);

        return self::SUCCESS;
    }

    private function show(SerpInsightDto $insight): void
    {
        $this->newLine();
        $this->table(['Kennzahl', 'Wert'], [
            ['Keyword', $insight->keyword],
            ['Suchvolumen/Monat', $insight->searchVolume === null ? '–' : number_format($insight->searchVolume, 0, ',', '.')],
            ['CPC (USD)', $insight->cpc === null ? '–' : number_format($insight->cpc, 2)],
            ['Wettbewerb', ($insight->competition ?? '–').' ('.($insight->competitionIndex ?? '–').')'],
            ['Median-Wortzahl', $insight->medianWordCount() ?? '–'],
            ['Abgerufen', $insight->fetchedAt?->toDateTimeString() ?? '–'],
        ]);

        $this->info('Top-Ergebnisse');
        $this->table(['#', 'Domain', 'Titel', 'H2', 'Woerter', 'Eigenes Netz'], array_map(
            static fn (array $result) => [
                $result['position'],
                $result['domain'],
                mb_strimwidth($result['title'], 0, 50, '…'),
                count($result['h2s']),
                $result['word_count'] ?: '–',
                $result['own_network'] ? 'ja' : '',
            ],
            $insight->topResults,
        ));

        $this->list('People Also Ask ('.count($insight->paa).')', $insight->paa);
        $this->list('Related Searches ('.count($insight->related).')', $insight->related);
        $this->list('Autocomplete ('.count($insight->autocomplete).')', $insight->autocomplete);
        $this->list('H2 der Wettbewerber ('.count($insight->competitorHeadings()).')', $insight->competitorHeadings());
    }

    /**
     * @param  array<int, string>  $values
     */
    private function list(string $title, array $values): void
    {
        $this->newLine();
        $this->info($title);

        if ($values === []) {
            $this->line('  –');

            return;
        }

        foreach ($values as $value) {
            $this->line("  • {$value}");
        }
    }

    private function tenant(): ?Tenant
    {
        $value = $this->option('tenant');

        if ($value === null) {
            $tenant = Tenant::query()->orderBy('id')->first();

            if (! $tenant instanceof Tenant) {
                $this->error('Kein Mandant vorhanden.');

                return null;
            }

            return $tenant;
        }

        $tenant = is_numeric($value)
            ? Tenant::query()->find((int) $value)
            : Tenant::query()->where('uuid', $value)->orWhere('domain', $value)->first();

        if (! $tenant instanceof Tenant) {
            $this->error("Mandant '{$value}' nicht gefunden.");

            return null;
        }

        return $tenant;
    }
}
