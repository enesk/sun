<?php

declare(strict_types=1);

namespace App\Guide\Support;

use App\Guide\Models\Central\PromptTemplate;
use App\Guide\Models\TenantGuideSetting;
use App\Models\Tenant;

/**
 * Variablen des System-Prompts "Ratgeber-Redaktion", die in jeder Stufe
 * gefuellt sein muessen (docs/guide-prompts.md §2): tenant_name, branch,
 * styleguide. Erwartet einen initialisierten Tenant-Kontext.
 */
final class TenantPromptVars
{
    /**
     * @return array{tenant_name: string, branch: string, styleguide: string}
     */
    public static function current(?TenantGuideSetting $setting = null): array
    {
        /** @var Tenant $tenant */
        $tenant = tenant();
        $setting ??= TenantGuideSetting::current();
        $branchKey = BranchResolver::resolve($tenant);

        $branch = trim((string) $setting->branch);

        if ($branch === '') {
            $branch = $branchKey !== null ? (string) BranchResolver::label($branchKey) : (string) $tenant->name;
        }

        $styleguide = $branchKey === null ? null : PromptTemplate::query()
            ->resolve("guide.styleguide_{$branchKey}", (int) $tenant->getKey())
            ->value('user_prompt');

        return [
            'tenant_name' => (string) $tenant->name,
            'branch' => $branch,
            'styleguide' => (string) ($styleguide ?? ''),
        ];
    }
}
