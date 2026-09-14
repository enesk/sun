<?php

declare(strict_types=1);

namespace App\Console\Commands\Seo\Concerns;

use App\Models\Tenant;
use Closure;
use Illuminate\Support\Str;

/**
 * Mandantenschleife der SEO-Kommandos (#2).
 *
 * Unter 'tenants:run' ist der Mandant schon initialisiert — dann laeuft das
 * Kommando nur fuer ihn, sonst wuerde jeder Mandant jeden anderen erneut
 * durchlaufen. Direkt aufgerufen gilt --tenants= (ID, UUID oder Domain),
 * ohne Angabe alle Portale.
 */
trait RunsForPortals
{
    /**
     * @param  Closure(Tenant): int  $callback
     */
    protected function runForPortals(Closure $callback): int
    {
        if (tenancy()->initialized) {
            /** @var Tenant $tenant */
            $tenant = tenant();

            return $callback($tenant);
        }

        $needles = array_filter((array) $this->option('tenants'), fn ($value): bool => (string) $value !== '');
        $tenants = $needles === [] ? Tenant::query()->orderBy('id')->get()->all() : $this->resolvePortals($needles);

        if ($tenants === null) {
            return self::FAILURE;
        }

        $exitCode = self::SUCCESS;

        /** @var Tenant $tenant */
        foreach ($tenants as $tenant) {
            $this->newLine();
            $this->info("[{$tenant->getKey()}] {$tenant->name} ({$tenant->domain})");

            $result = (int) $tenant->run(fn (): int => $callback($tenant));

            if ($result !== self::SUCCESS) {
                $exitCode = $result;
            }
        }

        return $exitCode;
    }

    /**
     * @param  array<int, string>  $needles
     * @return list<Tenant>|null
     */
    private function resolvePortals(array $needles): ?array
    {
        $tenants = [];

        foreach ($needles as $needle) {
            $tenant = is_numeric($needle)
                ? Tenant::query()->find((int) $needle)
                : Tenant::query()->where('uuid', $needle)->orWhere('domain', $needle)->first();

            if ($tenant === null) {
                $this->error("Portal '{$needle}' nicht gefunden.");

                return null;
            }

            $tenants[$tenant->getKey()] = $tenant;
        }

        return array_values($tenants);
    }

    /**
     * Pfad unterhalb des zentralen storage/ — unter Tenancy haengt storage_path()
     * sonst den Mandanten-Suffix an.
     */
    protected function centralStoragePath(string $path): string
    {
        if (! tenancy()->initialized) {
            return storage_path($path);
        }

        return (string) tenancy()->central(fn (): string => storage_path($path));
    }

    protected function portalSlug(Tenant $tenant): string
    {
        return Str::slug(str_replace('.', '-', (string) ($tenant->domain ?: $tenant->name))) ?: (string) $tenant->getKey();
    }
}
