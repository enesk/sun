<?php

declare(strict_types=1);

namespace App\Companies\Deletion\Steps;

use App\Companies\Deletion\DeletionContext;
use App\Companies\Deletion\DeletionStep;
use App\Companies\Deletion\StepResult;
use App\Jobs\GenerateTenantSitemapJob;

/**
 * Sitemap des Portals neu erzeugen (#8).
 *
 * Das geloeschte Profil faellt aus `Company::active()` und damit aus der
 * Sitemap; Stadt- und Kategorieseiten bekommen ein neues `lastmod`. Google
 * nimmt kein IndexNow an und erfaehrt die Entfernung genau darueber — plus
 * den 410 beim naechsten Crawl.
 *
 * Standard ist die Queue: ein Portal mit 19 600 Betrieben braucht dafuer
 * Minuten, und ein Loeschklick im Panel soll darauf nicht warten. Fuer die
 * Abnahme auf Staging schaltet `--sitemap=sync` (Artisan) bzw.
 * `profile-requests.deletion.sitemap` auf den synchronen Lauf — nur dann ist
 * direkt nach dem Lauf pruefbar, dass die URL aus der Datei verschwunden ist.
 */
class RegenerateSitemap implements DeletionStep
{
    public const MODE_QUEUE = 'queue';

    public const MODE_SYNC = 'sync';

    public const MODE_OFF = 'off';

    public function key(): string
    {
        return 'regenerate_sitemap';
    }

    public function label(): string
    {
        return 'Sitemap neu erzeugen';
    }

    public function plan(DeletionContext $context): string
    {
        return match ($this->mode($context)) {
            self::MODE_SYNC => 'Sitemap sofort neu erzeugen (synchron, kann Minuten dauern).',
            self::MODE_OFF => 'Sitemap nicht anfassen (abgeschaltet) — die URL bleibt bis zum nächsten geplanten Lauf drin.',
            default => 'GenerateTenantSitemapJob in die Queue legen.',
        };
    }

    public function run(DeletionContext $context): StepResult
    {
        $mode = $this->mode($context);
        $tenantId = (string) $context->tenant->getKey();

        if ($mode === self::MODE_OFF) {
            return StepResult::skipped('Sitemap-Neuaufbau ist abgeschaltet (profile-requests.deletion.sitemap).');
        }

        if ($mode === self::MODE_SYNC) {
            GenerateTenantSitemapJob::dispatchSync($tenantId);

            return StepResult::ok('Sitemap neu erzeugt (synchron).', ['mode' => $mode]);
        }

        GenerateTenantSitemapJob::dispatch($tenantId);

        return StepResult::ok('Sitemap-Neuaufbau in die Queue gelegt.', ['mode' => $mode]);
    }

    private function mode(DeletionContext $context): string
    {
        $mode = (string) ($context->note('sitemap_mode') ?? config('profile-requests.deletion.sitemap', self::MODE_QUEUE));

        return in_array($mode, [self::MODE_QUEUE, self::MODE_SYNC, self::MODE_OFF], true) ? $mode : self::MODE_QUEUE;
    }
}
