<?php

namespace App\Console\Commands;

use App\Models\Portal\Review;
use App\Models\Tenant;
use Illuminate\Console\Command;

class ApproveAllReviews extends Command
{
    protected $signature = 'reviews:approve-all
        {--tenant= : Nur fuer einen bestimmten Tenant ausfuehren}
        {--dry-run : Nur anzeigen was passieren wuerde, nichts aendern}';

    protected $description = 'Genehmigt alle unveröffentlichten Bewertungen bei allen Tenants';

    public function handle(): int
    {
        $tenantId = $this->option('tenant');
        $dryRun = $this->option('dry-run');

        $tenants = $tenantId
            ? Tenant::where('id', $tenantId)->get()
            : Tenant::all();

        if ($tenants->isEmpty()) {
            $this->warn('Keine Tenants gefunden.');
            return self::FAILURE;
        }

        if ($dryRun) {
            $this->warn('DRY RUN — keine Änderungen werden vorgenommen.');
        }

        $totalApproved = 0;

        foreach ($tenants as $tenant) {
            $count = $this->approveForTenant($tenant, $dryRun);
            $totalApproved += $count;
        }

        $this->newLine();
        $this->info("Fertig: {$totalApproved} Bewertungen genehmigt.");

        return self::SUCCESS;
    }

    private function approveForTenant(Tenant $tenant, bool $dryRun): int
    {
        $approved = 0;

        $tenant->run(function () use ($tenant, $dryRun, &$approved) {
            $pendingReviews = Review::on('tenant')
                ->where('moderation_status', '!=', Review::STATUS_APPROVED)
                ->with('company')
                ->get();

            if ($pendingReviews->isEmpty()) {
                $this->line("  {$tenant->name}: Keine unveröffentlichten Bewertungen.");
                return;
            }

            $this->info("  {$tenant->name}: {$pendingReviews->count()} unveröffentlichte Bewertungen gefunden.");

            if ($dryRun) {
                return;
            }

            foreach ($pendingReviews as $review) {
                $review->approve('System (Massen-Genehmigung)');
                $approved++;
            }

            $this->info("  {$tenant->name}: {$approved} Bewertungen genehmigt.");
        });

        return $approved;
    }
}
