<?php

declare(strict_types=1);

namespace App\Console\Commands\ProfileDescriptions;

use App\Console\Commands\ProfileDescriptions\Concerns\ResolvesPortal;
use App\Models\Portal\ProfileDescriptionRewrite;
use App\Services\ProfileDescriptions\DescriptionApplier;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;

/**
 * Stellt die gesicherten Originalbeschreibungen wieder her.
 *
 *   php artisan profiles:restore-descriptions --tenant=elektrikerportal --ids=12,34
 *   php artisan profiles:restore-descriptions --tenant=elektrikerportal --all
 *
 * Der erzeugte Text bleibt in profile_description_rewrites stehen und kann
 * mit profiles:apply-descriptions erneut uebernommen werden.
 */
class RestoreProfileDescriptions extends Command
{
    use ResolvesPortal;

    protected $signature = 'profiles:restore-descriptions
        {--tenant= : Portal (ID, UUID, Domain oder Domain ohne Endung), Pflicht}
        {--ids= : Nur diese Firmen-IDs, kommagetrennt}
        {--all : Alle uebernommenen Beschreibungen zuruecksetzen}
        {--dry-run : Nur zaehlen, nichts schreiben}';

    protected $description = 'Stellt die Originalbeschreibungen der Firmenprofile wieder her';

    public function handle(DescriptionApplier $applier): int
    {
        $ids = $this->idsOption();

        if ($ids === [] && ! $this->option('all')) {
            $this->error('--ids oder --all angeben.');

            return self::FAILURE;
        }

        $tenant = $this->resolvePortal();

        if ($tenant === null) {
            return self::FAILURE;
        }

        $this->info("{$tenant->name} ({$tenant->domain})");

        return (int) $tenant->run(function () use ($applier, $ids): int {
            $query = ProfileDescriptionRewrite::query()
                ->whereNotNull('applied_at')
                ->when($ids !== [], fn (Builder $query) => $query->whereIn('company_id', $ids));

            $total = $query->count();

            if ($this->option('dry-run')) {
                $this->info("{$total} Beschreibungen wuerden wiederhergestellt.");

                return self::SUCCESS;
            }

            if ($ids === [] && ! $this->confirm("{$total} Beschreibungen wiederherstellen?", true)) {
                return self::FAILURE;
            }

            $restored = 0;
            $bar = $this->output->createProgressBar($total);

            $query->with('company')->lazyById(200, 'id')->each(function (ProfileDescriptionRewrite $rewrite) use ($applier, &$restored, $bar): void {
                if ($applier->restore($rewrite) === null) {
                    $restored++;
                }

                $bar->advance();
            });

            $bar->finish();
            $this->newLine(2);
            $this->info("{$restored} Beschreibungen wiederhergestellt.");

            return self::SUCCESS;
        });
    }
}
