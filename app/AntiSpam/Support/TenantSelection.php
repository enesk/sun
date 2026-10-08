<?php

declare(strict_types=1);

namespace App\AntiSpam\Support;

use App\Models\Tenant;
use Illuminate\Support\Collection;

/**
 * Portalauswahl der Bereinigungs-Commands (#10).
 *
 * `--tenant` nimmt ID, UUID, Name oder Domain und darf mehrfach stehen. Ohne
 * Angabe — und ausdruecklich auch bei `--tenant=*`, wie im Ticket
 * geschrieben — gelten alle Portale. Die Schreibweise mit Sternchen gibt die
 * Shell unverändert weiter, solange kein gleichnamiges Verzeichnis existiert;
 * sie wird hier deshalb wie "keine Angabe" behandelt und nicht wie ein
 * Portalname.
 */
final class TenantSelection
{
    /**
     * @param  array<int, mixed>  $option  Wert von $this->option('tenant')
     * @return array<int, string>
     */
    public static function filter(array $option): array
    {
        return array_values(array_filter(
            array_map(static fn (mixed $value): string => mb_strtolower(trim((string) $value)), $option),
            static fn (string $value): bool => $value !== '' && $value !== '*',
        ));
    }

    /**
     * @param  array<int, mixed>  $option
     * @return Collection<int, Tenant>
     */
    public static function resolve(array $option): Collection
    {
        $filter = self::filter($option);

        /** @var Collection<int, Tenant> $tenants */
        $tenants = Tenant::query()->orderBy('name')->get();

        if ($filter === []) {
            return $tenants;
        }

        return $tenants->filter(static function (Tenant $tenant) use ($filter): bool {
            $haystack = [
                (string) $tenant->id,
                mb_strtolower((string) $tenant->uuid),
                mb_strtolower((string) $tenant->name),
                mb_strtolower((string) $tenant->domain),
            ];

            return array_intersect($filter, $haystack) !== [];
        })->values();
    }
}
