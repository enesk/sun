<?php

declare(strict_types=1);

namespace App\Services\ProfileDescriptions;

use App\Models\Portal\ProfileDescriptionRewrite;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Uebernimmt erzeugte Texte in companies.description und stellt sie wieder her.
 *
 * Uebernommen wird nur, wenn die Beschreibung der Firma noch dem gesicherten
 * Original entspricht — hat jemand sie zwischenzeitlich geaendert, bleibt sie
 * stehen. Muss im Kontext des Tenants laufen.
 */
final class DescriptionApplier
{
    /**
     * @return string|null Grund, falls nicht uebernommen
     */
    public function apply(ProfileDescriptionRewrite $rewrite, bool $force = false): ?string
    {
        $company = $rewrite->company;

        if ($company === null) {
            return 'Firma existiert nicht mehr';
        }

        if ($rewrite->status !== ProfileDescriptionRewrite::STATUS_DONE || blank($rewrite->generated_description)) {
            return 'kein fertiger Text';
        }

        if ($rewrite->applied_at !== null) {
            return 'bereits uebernommen';
        }

        if (! $force && (string) $company->description !== (string) $rewrite->original_description) {
            return 'Beschreibung seit der Sicherung geaendert (--force ueberschreibt)';
        }

        DB::connection($company->getConnectionName())->transaction(function () use ($rewrite, $company): void {
            $rewrite->update([
                'original_description' => $company->description,
                'original_source' => $company->description_source,
                'applied_at' => now(),
            ]);

            $company->update([
                'description' => $rewrite->generated_description,
                'description_source' => (string) config('profile_descriptions.applied_source'),
            ]);
        });

        Log::channel('profile-descriptions')->info('Beschreibung uebernommen', [
            'tenant' => tenant()?->getTenantKey(),
            'company_id' => $company->id,
            'prompt_version' => $rewrite->prompt_version,
        ]);

        return null;
    }

    /**
     * @return string|null Grund, falls nicht wiederhergestellt
     */
    public function restore(ProfileDescriptionRewrite $rewrite): ?string
    {
        $company = $rewrite->company;

        if ($company === null) {
            return 'Firma existiert nicht mehr';
        }

        if ($rewrite->applied_at === null) {
            return 'nicht uebernommen';
        }

        DB::connection($company->getConnectionName())->transaction(function () use ($rewrite, $company): void {
            $company->update([
                'description' => $rewrite->original_description,
                'description_source' => $rewrite->original_source,
            ]);

            $rewrite->update(['applied_at' => null]);
        });

        Log::channel('profile-descriptions')->info('Beschreibung wiederhergestellt', [
            'tenant' => tenant()?->getTenantKey(),
            'company_id' => $company->id,
        ]);

        return null;
    }
}
