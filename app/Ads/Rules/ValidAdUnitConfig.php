<?php

declare(strict_types=1);

namespace App\Ads\Rules;

use App\Ads\AdProviderRegistry;
use App\Ads\Enums\AdProviderType;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * `config_json` eines Werbemittels gegen das Schema des gewaehlten Providers
 * (SUN-AD-003, Ticket #3): AdSense braucht eine numerische `slot_id`, eigenes
 * HTML einen `code` von hoechstens 20 kB samt Breite und Hoehe.
 *
 * Die Regeln kommen seit #4 aus dem Provider selbst
 * (App\Ads\Contracts\AdProvider::validateConfig()), nicht aus einer Liste
 * hier — ein neuer Provider braucht keine Aenderung an dieser Rule.
 */
class ValidAdUnitConfig implements ValidationRule
{
    public bool $implicit = true;

    public function __construct(private readonly AdProviderType|string|null $provider) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $key = $this->provider instanceof AdProviderType
            ? $this->provider->value
            : (string) $this->provider;

        $provider = $key === '' ? null : app(AdProviderRegistry::class)->get($key);

        if ($provider === null) {
            $fail(__('Bitte zuerst die Art des Werbemittels waehlen.'));

            return;
        }

        if (! is_array($value)) {
            $fail(__('Die Konfiguration des Werbemittels ist unvollstaendig.'));

            return;
        }

        foreach ($provider->validateConfig($value) as $error) {
            $fail($error);
        }
    }
}
