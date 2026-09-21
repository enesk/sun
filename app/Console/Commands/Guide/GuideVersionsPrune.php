<?php

declare(strict_types=1);

namespace App\Console\Commands\Guide;

use App\Guide\Publishing\VersionStore;
use App\Models\Tenant;
use Illuminate\Console\Command;
use Throwable;

/**
 * Begrenzt die Versionshistorie der Ratgeber-Artikel (#12) auf
 * guide.publishing.keep_versions (30) Fassungen je Artikel. Geloescht wird
 * von der aeltesten an; die erste und die gerade veroeffentlichte Fassung
 * bleiben immer. Fassungen ohne Artikel (Themen vor der ersten
 * Veroeffentlichung) werden nicht angefasst.
 *
 *   php artisan guide:versions:prune
 *   php artisan guide:versions:prune --tenant=sanitaerfinder.com --dry-run
 */
class GuideVersionsPrune extends Command
{
    protected $signature = 'guide:versions:prune
        {--tenant=* : Portale (ID, UUID oder Domain); ohne Angabe alle}
        {--keep= : Hoechstzahl Fassungen je Artikel (Vorgabe guide.publishing.keep_versions)}
        {--dry-run : Nur zaehlen, nichts loeschen}';

    protected $description = 'Loescht alte Fassungen der Ratgeber-Artikel ueber der Hoechstzahl je Artikel';

    public function handle(VersionStore $versions): int
    {
        $keep = (int) ($this->option('keep') ?? config('guide.publishing.keep_versions', 30));
        $dryRun = (bool) $this->option('dry-run');

        if ($keep < 2) {
            $this->error('--keep muss mindestens 2 sein (erste und aktuelle Fassung bleiben immer).');

            return self::FAILURE;
        }

        $needles = array_values(array_filter(array_map('trim', (array) $this->option('tenant'))));
        $tenants = Tenant::query()
            ->when($needles !== [], fn ($query) => $query->where(fn ($inner) => $inner
                ->whereIn('id', array_filter($needles, 'ctype_digit'))
                ->orWhereIn('uuid', $needles)
                ->orWhereIn('domain', $needles)))
            ->orderBy('id')
            ->get();

        $total = 0;
        $failed = false;

        /** @var Tenant $tenant */
        foreach ($tenants as $tenant) {
            try {
                $count = (int) $tenant->run(fn (): int => $versions->prune($keep, $dryRun));
            } catch (Throwable $exception) {
                report($exception);
                $this->error("{$tenant->domain}: {$exception->getMessage()}");
                $failed = true;

                continue;
            }

            $total += $count;

            if ($count > 0) {
                $this->line("{$tenant->domain}: {$count} Fassung(en)".($dryRun ? ' würden gelöscht' : ' gelöscht'));
            }
        }

        $this->info("{$total} Fassung(en)".($dryRun ? ' würden gelöscht.' : ' gelöscht.'));

        return $failed ? self::FAILURE : self::SUCCESS;
    }
}
