<?php

namespace App\Console\Commands;

use App\Models\Portal\Review;
use App\Models\Tenant;
use App\Services\Moderation\ReviewSpamDetector;
use Illuminate\Console\Command;

/**
 * Massen-Freigabe fuer Bewertungen im Status pending (#17).
 *
 * Bewusst nur pending: needs_review (Heuristik, Meldung) und rejected sind
 * Moderationsentscheidungen bzw. offene Verdachtsfaelle und bleiben unberuehrt.
 * Jede pending-Bewertung laeuft vorher erneut durch den ReviewSpamDetector,
 * weil Importe (withoutEvents) und Altbestand den creating-Hook nie gesehen
 * haben; Treffer gehen auf needs_review statt auf approved.
 */
class ApproveAllReviews extends Command
{
    protected $signature = 'reviews:approve-all
        {--tenant= : Nur fuer einen bestimmten Tenant ausfuehren}
        {--dry-run : Nur anzeigen was passieren wuerde, nichts aendern}';

    protected $description = 'Genehmigt ausstehende Bewertungen (pending) ohne Heuristik-Treffer; Treffer gehen in die Pruefung';

    public function handle(): int
    {
        $tenantId = $this->option('tenant');
        $dryRun = (bool) $this->option('dry-run');

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

        $detector = ReviewSpamDetector::fromConfig();
        $totalApproved = 0;
        $totalFlagged = 0;

        foreach ($tenants as $tenant) {
            [$approved, $flagged] = $this->approveForTenant($tenant, $detector, $dryRun);
            $totalApproved += $approved;
            $totalFlagged += $flagged;
        }

        $this->newLine();
        $verb = $dryRun ? 'würden' : 'wurden';
        $this->info("Fertig: {$totalApproved} Bewertungen {$verb} genehmigt, {$totalFlagged} {$verb} zur Prüfung markiert.");
        $this->line('needs_review und rejected werden von diesem Befehl nie angefasst.');

        return self::SUCCESS;
    }

    /**
     * @return array{0: int, 1: int} [freigegeben, zur Pruefung markiert]
     */
    private function approveForTenant(Tenant $tenant, ReviewSpamDetector $detector, bool $dryRun): array
    {
        $approved = 0;
        $flagged = 0;

        $tenant->run(function () use ($tenant, $detector, $dryRun, &$approved, &$flagged) {
            $pendingReviews = Review::on('tenant')->pending();

            if (! $pendingReviews->exists()) {
                $this->line("  {$tenant->name}: Keine ausstehenden Bewertungen.");

                return;
            }

            foreach ($pendingReviews->lazyById() as $review) {
                $reason = $detector->reasonFor($review);

                if ($reason !== null) {
                    $flagged++;

                    if (! $dryRun) {
                        $review->markForReview($reason);
                    }

                    continue;
                }

                $approved++;

                if (! $dryRun) {
                    $review->approve('System (Massen-Genehmigung)');
                }
            }

            $suffix = $dryRun ? ' (Trockenlauf)' : '';
            $this->info("  {$tenant->name}: {$approved} genehmigt, {$flagged} zur Prüfung markiert{$suffix}.");
        });

        return [$approved, $flagged];
    }
}
