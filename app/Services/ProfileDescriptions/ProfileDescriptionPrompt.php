<?php

declare(strict_types=1);

namespace App\Services\ProfileDescriptions;

use App\Models\Tenant;
use RuntimeException;

/**
 * System-Prompt aus resources/prompts/profile-description.md, befuellt mit
 * den Branchenbegriffen des Tenants (TenantTerms) und der Floskelliste aus
 * config/profile_descriptions.php.
 */
final class ProfileDescriptionPrompt
{
    public function version(): int
    {
        return (int) config('profile_descriptions.prompt_version');
    }

    public function system(Tenant $tenant): string
    {
        $path = (string) config('profile_descriptions.prompt_path');

        if (! is_file($path)) {
            throw new RuntimeException("Prompt-Datei fehlt: {$path}");
        }

        /** @var array<string, string> $terms */
        $terms = $tenant->terms;

        $phrases = array_map(
            static fn (string $phrase): string => "\"{$phrase}\"",
            (array) config('profile_descriptions.forbidden_phrases', []),
        );

        return strtr((string) file_get_contents($path), [
            '{{portal}}' => $terms['portal'],
            '{{branche}}' => $terms['branche'],
            '{{branchenbegriffe}}' => implode(', ', array_unique([
                $terms['branche'],
                $terms['branche_plural'],
                $terms['betrieb'],
                $terms['betrieb_plural'],
            ])),
            '{{verbotene_floskeln}}' => implode(', ', $phrases),
        ]);
    }

    /**
     * @param  list<array<string, mixed>>  $facts
     */
    public function input(array $facts): string
    {
        return json_encode($facts, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
    }
}
