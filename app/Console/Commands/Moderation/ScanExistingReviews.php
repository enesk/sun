<?php

declare(strict_types=1);

namespace App\Console\Commands\Moderation;

use App\Console\Commands\Seo\Concerns\RunsForPortals;
use App\Models\Portal\Review;
use App\Models\Tenant;
use App\Services\Moderation\ReviewSpamDetector;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;

/**
 * Einmaliger Bestandsscan der Bewertungen gegen den ReviewSpamDetector (#13).
 *
 * Vorgabe ist der Trockenlauf, geschrieben wird nur mit --apply (Muster wie
 * seo:clean-ai-footprints). Geprueft werden freigegebene und ausstehende
 * Bewertungen; Treffer gehen auf needs_review mit Grund, nie auf rejected.
 * Die Umstellung laeuft ueber das Model, damit der ReviewObserver Rating und
 * JSON-LD des Betriebs nachzieht. Je Portal entsteht ein CSV-Bericht unter
 * storage/app/moderation-scan/.
 *
 * Der Detector laeuft im Bestandsmodus (#19): Treffer ist nur ein
 * Bewerbungsschreiben (Stichwort plus Grussformel), siehe
 * ReviewSpamDetector::forExistingReviews().
 */
class ScanExistingReviews extends Command
{
    use RunsForPortals;

    protected $signature = 'moderation:scan-existing-reviews
        {--dry-run : Nur Bericht schreiben, nichts aendern (Vorgabe)}
        {--apply : Verdachtsfaelle tatsaechlich auf needs_review setzen}
        {--tenants=* : Portal-ID, UUID oder Domain (ohne Angabe alle Portale)}';

    protected $description = 'Prueft vorhandene Bewertungen mit der Keyword-Heuristik und setzt Verdachtsfaelle auf needs_review';

    private const EXCERPT_LENGTH = 300;

    public function handle(): int
    {
        if ($this->option('apply') && $this->option('dry-run')) {
            $this->error('--apply und --dry-run schliessen sich aus.');

            return self::FAILURE;
        }

        $apply = (bool) $this->option('apply');
        $detector = ReviewSpamDetector::forExistingReviews();
        $reportDir = $this->centralStoragePath('app/moderation-scan');

        if (! $apply) {
            $this->comment('Trockenlauf — es wird nichts geschrieben. Mit --apply speichern.');
        }

        return $this->runForPortals(fn (Tenant $tenant): int => $this->scanPortal($tenant, $detector, $reportDir, $apply));
    }

    private function scanPortal(Tenant $tenant, ReviewSpamDetector $detector, string $reportDir, bool $apply): int
    {
        $schema = Schema::connection((new Review)->getConnectionName());

        if (! $schema->hasTable('reviews') || ! $schema->hasColumn('reviews', 'moderation_reason')) {
            $this->warn('Tabelle reviews ohne Moderationsspalten — zuerst tenants:migrate ausfuehren. Uebersprungen.');

            return self::SUCCESS;
        }

        $checked = 0;
        $rows = [];
        $hits = [Review::STATUS_APPROVED => 0, Review::STATUS_PENDING => 0];

        Review::query()
            ->whereIn('moderation_status', [Review::STATUS_APPROVED, Review::STATUS_PENDING])
            ->with('company:id,slug,name')
            ->chunkById(1000, function (Collection $reviews) use ($detector, $apply, &$checked, &$rows, &$hits): void {
                /** @var Review $review */
                foreach ($reviews as $review) {
                    $checked++;
                    $reason = $detector->reasonFor($review);

                    if ($reason === null) {
                        continue;
                    }

                    $previous = $review->moderation_status;
                    $hits[$previous]++;

                    $rows[] = [
                        $review->id,
                        $review->company_id,
                        (string) $review->company?->slug,
                        (string) $review->company?->name,
                        $previous,
                        $reason,
                        trim((string) preg_replace('/\s+/u', ' ', mb_substr(trim("{$review->title} {$review->body}"), 0, self::EXCERPT_LENGTH))),
                    ];

                    if ($apply) {
                        $review->markForReview($reason);
                    }
                }
            });

        $file = $this->writeReport($tenant, $reportDir, $apply, $rows);

        $this->table(['Geprueft', 'Treffer', 'davon freigegeben', 'davon ausstehend', $apply ? 'Auf needs_review gesetzt' : 'Wuerde gesetzt'], [[
            $checked,
            count($rows),
            $hits[Review::STATUS_APPROVED],
            $hits[Review::STATUS_PENDING],
            count($rows),
        ]]);
        $this->line("Bericht: {$file}");

        return self::SUCCESS;
    }

    /**
     * @param  list<array<int, string|int>>  $rows
     */
    private function writeReport(Tenant $tenant, string $reportDir, bool $apply, array $rows): string
    {
        File::ensureDirectoryExists($reportDir);

        $file = sprintf('%s/%s-%s%s.csv', $reportDir, $this->portalSlug($tenant), now()->format('Y-m-d'), $apply ? '-apply' : '');
        $handle = fopen($file, 'w');

        // BOM, damit Excel die Umlaute richtig liest.
        fwrite($handle, "\xEF\xBB\xBF");
        fputcsv($handle, ['review_id', 'company_id', 'Slug', 'Betrieb', 'Status vorher', 'Grund', 'Auszug'], ';');

        foreach ($rows as $row) {
            fputcsv($handle, $row, ';');
        }

        fclose($handle);

        return $file;
    }
}
