<?php

declare(strict_types=1);

namespace App\Ads\Support;

use App\Ads\AdProviderRegistry;
use App\Ads\Contracts\AdProvider;
use App\Ads\Enums\AdProviderType;
use App\Ads\Providers\HtmlProvider;

/**
 * Zugang zum Schema der providerspezifischen Konfiguration eines Werbemittels
 * (`ad_units.config_json`).
 *
 * Seit Ticket #4 steht das Schema nicht mehr hier, sondern im Provider
 * ({@see AdProvider::configSchema()}) — sonst gaebe es zwei Stellen, die von
 * einem neuen Provider wissen muessten. Diese Klasse bleibt als eine
 * Anlaufstelle fuer Model, Rule (#3) und Formulare (#8/#10) und reicht alles
 * an die Registry weiter.
 */
final class AdUnitConfigSchema
{
    /** @deprecated Nutze HtmlProvider::MAX_BYTES. */
    public const HTML_MAX_BYTES = HtmlProvider::MAX_BYTES;

    /**
     * Felddefinitionen des Providers; leer, wenn er nicht registriert ist.
     *
     * @return list<array<string, mixed>>
     */
    public static function fields(AdProviderType|string|null $provider): array
    {
        return self::provider($provider)?->configSchema() ?? [];
    }

    /**
     * @param  array<string, mixed>  $config
     * @return list<string> leer = gueltig
     */
    public static function errors(AdProviderType|string|null $provider, array $config): array
    {
        $instance = self::provider($provider);

        if ($instance === null) {
            return [__('Unbekannte Art des Werbemittels.')];
        }

        return $instance->validateConfig($config);
    }

    /**
     * @param  array<string, mixed>  $config
     */
    public static function passes(AdProviderType|string|null $provider, array $config): bool
    {
        return self::errors($provider, $config) === [];
    }

    /**
     * Auf die bekannten Schluessel eingedampfte Konfiguration.
     *
     * @param  array<string, mixed>  $config
     * @return array<string, mixed>
     */
    public static function normalize(AdProviderType|string|null $provider, array $config): array
    {
        return self::provider($provider)?->normalizeConfig($config) ?? [];
    }

    private static function provider(AdProviderType|string|null $provider): ?AdProvider
    {
        if ($provider === null || $provider === '') {
            return null;
        }

        return app(AdProviderRegistry::class)->get(
            $provider instanceof AdProviderType ? $provider->value : $provider,
        );
    }
}
