<?php

declare(strict_types=1);

namespace App\Console\Commands\Guide;

use App\Guide\Import\ImportReport;
use App\Guide\Import\TopicListImporter;
use App\Guide\Models\Central\TopicList;
use Illuminate\Console\Command;
use InvalidArgumentException;

/**
 * Themen-Import ohne Dashboard (#6).
 *
 *   php artisan guide:topics:import themen.xlsx --list="Sanitaer Grundliste" --branch=Sanitaer
 *   php artisan guide:topics:import themen.csv --list=3        in vorhandene Liste 3 ergaenzen
 *   pbpaste | php artisan guide:topics:import - --list=3      eingefuegter Text von STDIN
 *
 * Formate: .csv/.tsv (Trennzeichen ; , oder Tab), .xlsx (erstes Blatt),
 * .txt und STDIN (eine Frage je Zeile, optional "Kategorie; Frage; H2 | > H3 | H2").
 */
class GuideTopicsImport extends Command
{
    protected $signature = 'guide:topics:import
        {file : Pfad zu CSV, TSV, XLSX oder TXT; "-" liest eingefuegten Text von STDIN}
        {--list= : ID oder Name der Themenliste; ohne Angabe wird eine Liste nach dem Dateinamen angelegt}
        {--branch= : Branche einer neu angelegten Liste}';

    protected $description = 'Importiert eine Themenliste des Ratgebersystems aus CSV, XLSX oder Text';

    public function handle(TopicListImporter $importer): int
    {
        $file = (string) $this->argument('file');
        $list = $this->topicList($file);

        try {
            $report = $file === '-'
                ? $importer->importText($list, (string) stream_get_contents(STDIN))
                : $importer->importFile($list, $file);
        } catch (InvalidArgumentException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->printReport($list, $report);

        return self::SUCCESS;
    }

    private function topicList(string $file): TopicList
    {
        $needle = trim((string) $this->option('list'));

        if ($needle !== '' && ctype_digit($needle)) {
            $list = TopicList::query()->find((int) $needle);

            if ($list !== null) {
                return $list;
            }
        }

        $name = $needle !== '' ? $needle : pathinfo($file === '-' ? 'Eingefügte Themen' : $file, PATHINFO_FILENAME);

        return TopicList::query()->firstOrCreate(['name' => $name], [
            'branch' => $this->option('branch') ?: null,
        ]);
    }

    private function printReport(TopicList $list, ImportReport $report): void
    {
        $this->info(sprintf('Themenliste #%d „%s“', (int) $list->getKey(), (string) $list->name));
        $this->line("  importiert:              {$report->imported}");
        $this->line("  aktualisiert:            {$report->updated}");
        $this->line("  Duplikate übersprungen:  {$report->skippedDuplicates}");
        $this->line('  Fehler:                  '.count($report->errors));

        if ($report->hasErrors()) {
            $this->newLine();
            $this->table(['Zeile', 'Grund'], array_map(
                static fn (array $error): array => [$error['line'], $error['reason']],
                $report->errors,
            ));
        }
    }
}
