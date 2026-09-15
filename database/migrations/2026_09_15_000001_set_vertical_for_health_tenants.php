<?php

use App\Support\Tenancy\TenantVertical;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Setzt die Vertikale 'gesundheit' fuer die bestehenden Gesundheitsportale (#14).
 *
 * Nur Tenants ohne gespeicherte Vertikale werden angefasst; eine im Formular
 * gewaehlte Vertikale bleibt unberuehrt. Alle anderen Tenants brauchen keinen
 * Eintrag, fehlend ergibt 'handwerk'. Bewusst ueber die Query statt das Model,
 * damit keine Tenant-Events (Sync, Theme) mitlaufen.
 */
return new class extends Migration
{
    /**
     * Produktionsdomains aus database/data/tenant_terms.php.
     */
    private const DOMAINS = [
        'tierarztportal.com',
        'apotheke.firmenfreund.de',
        'unfallarzt.firmenfreund.de',
        'zahnarzt.firmenfreund.de',
        'arztfinder.firmenfreund.de',
    ];

    public function up(): void
    {
        DB::table('tenants')
            ->whereIn('domain', self::DOMAINS)
            ->orderBy('id')
            ->get(['id', 'data'])
            ->each(function (object $tenant): void {
                $data = json_decode((string) $tenant->data, true);
                $data = is_array($data) ? $data : [];

                if (isset($data[TenantVertical::ATTRIBUTE])) {
                    return;
                }

                $data[TenantVertical::ATTRIBUTE] = TenantVertical::GESUNDHEIT;

                DB::table('tenants')
                    ->where('id', $tenant->id)
                    ->update(['data' => json_encode($data, JSON_UNESCAPED_UNICODE)]);
            });
    }

    public function down(): void
    {
        DB::table('tenants')
            ->whereIn('domain', self::DOMAINS)
            ->orderBy('id')
            ->get(['id', 'data'])
            ->each(function (object $tenant): void {
                $data = json_decode((string) $tenant->data, true);

                if (! is_array($data) || ($data[TenantVertical::ATTRIBUTE] ?? null) !== TenantVertical::GESUNDHEIT) {
                    return;
                }

                unset($data[TenantVertical::ATTRIBUTE]);

                DB::table('tenants')
                    ->where('id', $tenant->id)
                    ->update(['data' => json_encode($data, JSON_UNESCAPED_UNICODE)]);
            });
    }
};
