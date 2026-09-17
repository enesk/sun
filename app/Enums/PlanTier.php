<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Plan-Stufe eines Betriebs im Premium-Modul (#2).
 *
 * Freischaltungen und Limits je Stufe stehen ausschliesslich in
 * config/premium.php, nie im Enum selbst.
 */
enum PlanTier: string
{
    case Free = 'free';
    case Pro = 'pro';
    case Premium = 'premium';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    /**
     * @return array<string, string>
     */
    public static function options(): array
    {
        return collect(self::cases())
            ->mapWithKeys(fn (self $tier): array => [$tier->value => $tier->label()])
            ->all();
    }

    /**
     * Stufe zu einem SaasyKit-Plan-Slug laut config('premium.plan_tiers').
     * Unbekannte Slugs ergeben null, nicht Free.
     */
    public static function forPlanSlug(?string $slug): ?self
    {
        $value = config("premium.plan_tiers.{$slug}");

        return is_string($value) ? self::tryFrom($value) : null;
    }

    public function label(): string
    {
        return match ($this) {
            self::Free => 'Basis',
            self::Pro => 'Pro',
            self::Premium => 'Premium',
        };
    }

    public function rank(): int
    {
        return match ($this) {
            self::Free => 0,
            self::Pro => 1,
            self::Premium => 2,
        };
    }

    public function isAtLeast(self $other): bool
    {
        return $this->rank() >= $other->rank();
    }

    /**
     * @return list<PremiumFeature>
     */
    public function features(): array
    {
        return array_values(array_filter(array_map(
            fn (mixed $value): ?PremiumFeature => is_string($value) ? PremiumFeature::tryFrom($value) : null,
            (array) config("premium.tiers.{$this->value}.features", []),
        )));
    }

    public function hasFeature(PremiumFeature $feature): bool
    {
        return in_array($feature, $this->features(), true);
    }

    /**
     * Numerisches Limit der Stufe; null bedeutet unbegrenzt.
     */
    public function limit(string $key): ?int
    {
        $value = config("premium.tiers.{$this->value}.limits.{$key}");

        return $value === null ? null : (int) $value;
    }
}
