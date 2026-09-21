<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;

/**
 * Frueherer Rollout-Schalter der alten Content-Pipeline (#26). Er schrieb in
 * tenant_content_settings, die das Ratgebersystem nicht liest. Bleibt als
 * Verweis stehen, damit alte Notizen und Runbooks nicht still ins Leere
 * schalten (#38 G8, design/guide-dashboard.md §11.4).
 */
class ContentRollout extends Command
{
    protected $signature = 'content:rollout
        {--activate=* : wirkungslos}
        {--deactivate=* : wirkungslos}
        {--activate-all : wirkungslos}
        {--deactivate-all : wirkungslos}
        {--threshold= : wirkungslos}';

    protected $description = 'Wirkt nicht mehr – verwende guide:rollout';

    public function handle(): int
    {
        $this->error('Dieser Befehl wirkt nicht mehr. Verwende guide:rollout.');

        return self::FAILURE;
    }
}
