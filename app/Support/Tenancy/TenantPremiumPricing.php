<?php

declare(strict_types=1);

namespace App\Support\Tenancy;

use App\Models\Tenant;

/**
 * Preiskonfiguration des Premium-Moduls je Portal (#2).
 *
 * Gespeichert in der Stancl-data-Spalte unter `premium_pricing`:
 * je Preis-Key (config('premium.price_plans')) eine Stripe-Preis-ID
 * (`<key>_stripe_price_id`) und der angezeigte Bruttopreis in Cent
 * (`<key>_gross_cents`). Fehlt fuer einen Key eines der beiden Felder, ist
 * dieser Preis auf dem Portal nicht kaeuflich; fehlt ein Abo-Preis, gilt der
 * Verkauf auf dem Portal als deaktiviert (Preisseite: "Bald verfügbar").
 */
final class TenantPremiumPricing
{
    public const ATTRIBUTE = 'premium_pricing';

    public const STRIPE_SUFFIX = '_stripe_price_id';

    public const GROSS_SUFFIX = '_gross_cents';

    public const ADDON_KEYS = ['featured_monthly'];

    /**
     * @param  array<string, mixed>  $values
     */
    private function __construct(private readonly array $values) {}

    public static function for(?Tenant $tenant): self
    {
        $values = $tenant?->getAttribute(self::ATTRIBUTE);

        if (is_string($values)) {
            $values = json_decode($values, true);
        }

        return new self(is_array($values) ? $values : []);
    }

    public static function current(): self
    {
        $tenant = tenant();

        return self::for($tenant instanceof Tenant ? $tenant : null);
    }

    /**
     * @return list<string>
     */
    public static function keys(): array
    {
        return array_keys((array) config('premium.price_plans', []));
    }

    /**
     * @return list<string>
     */
    public static function subscriptionKeys(): array
    {
        return array_values(array_diff(self::keys(), self::ADDON_KEYS));
    }

    public static function label(string $key): string
    {
        return match ($key) {
            'pro_monthly' => 'Pro monatlich',
            'pro_yearly' => 'Pro jährlich',
            'premium_monthly' => 'Premium monatlich',
            'premium_yearly' => 'Premium jährlich',
            'featured_monthly' => 'Top-Platzierung monatlich',
            default => $key,
        };
    }

    public function stripePriceId(string $key): ?string
    {
        $id = trim((string) ($this->values[$key.self::STRIPE_SUFFIX] ?? ''));

        return $id === '' ? null : $id;
    }

    /**
     * Angezeigter Bruttopreis in Cent, null wenn nicht gepflegt.
     */
    public function grossCents(string $key): ?int
    {
        $cents = $this->values[$key.self::GROSS_SUFFIX] ?? null;

        if (! is_numeric($cents) || (int) $cents <= 0) {
            return null;
        }

        return (int) $cents;
    }

    public function isAvailable(string $key): bool
    {
        return $this->stripePriceId($key) !== null && $this->grossCents($key) !== null;
    }

    /**
     * Verkauf ist nur aktiv, wenn alle Abo-Preise vollstaendig gepflegt sind.
     */
    public function isSaleEnabled(): bool
    {
        foreach (self::subscriptionKeys() as $key) {
            if (! $this->isAvailable($key)) {
                return false;
            }
        }

        return true;
    }

    public function isFeaturedSaleEnabled(): bool
    {
        return $this->isSaleEnabled() && $this->isAvailable('featured_monthly');
    }

    public static function planSlug(string $key): ?string
    {
        $slug = config("premium.price_plans.{$key}");

        return is_string($slug) ? $slug : null;
    }
}
