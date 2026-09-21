<?php

declare(strict_types=1);

namespace App\Console\Commands\Guide;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

/**
 * Loescht hochgeladene Themenlisten aus dem Import-Wizard (#15) nach
 * sieben Tagen. Die Dateien liegen central unter storage/app/guide-imports;
 * die importierten Themen stehen danach in guide_topic_list_items.
 */
class GuideImportsPrune extends Command
{
    public const DIRECTORY = 'guide-imports';

    public const KEEP_DAYS = 7;

    protected $signature = 'guide:imports:prune';

    protected $description = 'Loescht hochgeladene Themenlisten des Ratgeber-Imports, die aelter als 7 Tage sind';

    public function handle(): int
    {
        $disk = Storage::disk('local');
        $threshold = now()->subDays(self::KEEP_DAYS)->getTimestamp();
        $deleted = 0;

        foreach ($disk->allFiles(self::DIRECTORY) as $file) {
            if ($disk->lastModified($file) < $threshold && $disk->delete($file)) {
                $deleted++;
            }
        }

        $this->info("{$deleted} Datei(en) gelöscht.");

        return self::SUCCESS;
    }
}
