<?php

declare(strict_types=1);

namespace App\Ads\Jobs;

use App\Ads\Support\AdCache;
use App\Models\Tenant;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Aufgeloeste Belegungen vergessen (docs/ad-slots.md 8, Ticket #4).
 *
 * Leeren heisst: je Portal in dessen Kontext wechseln und sechs Schluessel
 * (ein Seitentyp je Schluessel) vergessen. Bei einer netzweiten Belegung sind
 * das alle 20+ Portale — das gehoert in einen Job und nicht in den Request,
 * der gerade "Speichern" geklickt hat.
 *
 * Portalgenaue Aenderungen leert der Observer dagegen sofort im Request
 * (App\Ads\Observers\AdCacheInvalidator), damit die naechste Seitenansicht
 * schon stimmt, ohne auf einen Worker zu warten.
 */
class FlushAdCache implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $timeout = 300;

    public array $backoff = [10, 30, 60];

    /**
     * @param  list<int|string>|null  $tenantIds  null = alle Portale
     */
    public function __construct(public readonly ?array $tenantIds = null) {}

    public function handle(): void
    {
        if ($this->tenantIds === null) {
            AdCache::forgetAll();

            return;
        }

        foreach (Tenant::query()->whereKey($this->tenantIds)->cursor() as $tenant) {
            AdCache::forgetTenant($tenant);
        }
    }
}
