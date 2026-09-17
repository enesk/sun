<?php

declare(strict_types=1);

namespace App\Console\Commands\ProfileDescriptions;

use App\Console\Commands\ProfileDescriptions\Concerns\ResolvesPortal;
use App\Models\Portal\ProfileDescriptionRewrite;
use App\Services\ProfileDescriptions\DescriptionApplier;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;

/**
 * Uebernimmt fertige Texte aus profile_description_rewrites in die Profile.
 *
 *   php artisan profiles:apply-descriptions --tenant=elektrikerportal --dry-run
 *   php artisan profiles:apply-descriptions --tenant=elektrikerportal
 *   php artisan profiles:apply-descriptions --tenant=elektrikerportal --ids=12,34
 *
 * Eine seit der Sicherung geaenderte Beschreibung bleibt stehen, ausser --force.
 */
class ApplyProfileDescriptions extends Command
{
    use ResolvesPortal;

    protected $signature = 'profiles:apply-descriptions
        {--tenant= : Portal (ID, UUID, Domain oder Domain ohne Endung), Pflicht}
        {--ids= : Nur diese Firmen-IDs, kommagetrennt}
        {--limit= : Hoechstens so viele Profile}
        {--force : Auch uebernehmen, wenn die Beschreibung seit der Sicherung geaendert wurde}
        {--dry-run : Nur zaehlen, nichts schreiben}';

    protected $description = 'Uebernimmt neu geschriebene Beschreibungen in die Firmenprofile';

    public function handle(DescriptionApplier $applier): int
    {
        $tenant = $this->resolvePortal();

        if ($tenant === null) {
            return self::FAILURE;
        }

        $this->info("{$tenant->name} ({$tenant->domain})");

        return (int) $tenant->run(fn (): int => $this->applyForTenant($applier));
    }

    private function applyForTenant(DescriptionApplier $applier): int
    {
        $ids = $this->idsOption();
        $query = ProfileDescriptionRewrite::query()
            ->where('status', ProfileDescriptionRewrite::STATUS_DONE)
            ->whereNull('applied_at')
            ->when($ids !== [], fn (Builder $query) => $query->whereIn('company_id', $ids))
            ->orderBy('company_id');

        $total = $query->count();
        $limit = $this->option('limit');
        $total = $limit === null ? $total : min($total, (int) $limit);

        if ($this->option('dry-run')) {
            $this->info("{$total} fertige Texte wuerden uebernommen.");

            return self::SUCCESS;
        }

        $applied = 0;
        $kept = [];
        $bar = $this->output->createProgressBar($total);

        foreach ($query->limit($total)->pluck('id')->chunk(200) as $chunk) {
            ProfileDescriptionRewrite::query()->with('company')->whereKey($chunk->all())->orderBy('company_id')->get()
                ->each(function (ProfileDescriptionRewrite $rewrite) use ($applier, &$applied, &$kept, $bar): void {
                    $reason = $applier->apply($rewrite, (bool) $this->option('force'));
                    $reason === null ? $applied++ : $kept[$reason] = ($kept[$reason] ?? 0) + 1;
                    $bar->advance();
                });
        }

        $bar->finish();
        $this->newLine(2);
        $this->info("{$applied} uebernommen.");

        foreach ($kept as $reason => $count) {
            $this->warn("{$count} nicht uebernommen: {$reason}");
        }

        return self::SUCCESS;
    }
}
