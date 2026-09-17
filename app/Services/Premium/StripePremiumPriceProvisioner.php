<?php

declare(strict_types=1);

namespace App\Services\Premium;

use Stripe\StripeClient;

/**
 * Legt die Stripe-Products und -Prices der Premium-Plaene an (#38).
 *
 * Idempotent: Das Product hat eine feste ID (`sun-<plan-slug>`), der Price
 * einen `lookup_key` aus Preis-Key und Betrag. Ein zweiter Lauf findet beide
 * wieder; ein geaenderter Betrag ergibt einen neuen lookup_key und damit einen
 * neuen Price. Aeltere Prices desselben Keys werden deaktiviert, nicht
 * geloescht: laufende Abos rechnen in Stripe weiter darueber ab.
 */
class StripePremiumPriceProvisioner
{
    public function __construct(private readonly StripeClient $stripe) {}

    public static function lookupKey(string $priceKey, int $grossCents): string
    {
        return "sun_{$priceKey}_{$grossCents}";
    }

    public static function productId(string $planSlug): string
    {
        return "sun-{$planSlug}";
    }

    /**
     * Vorhandene Price-ID zum lookup_key, null wenn es ihn noch nicht gibt.
     */
    public function find(string $priceKey, int $grossCents): ?string
    {
        $prices = $this->stripe->prices->all([
            'lookup_keys' => [self::lookupKey($priceKey, $grossCents)],
            'active' => true,
            'limit' => 1,
        ]);

        return $prices->data[0]->id ?? null;
    }

    /**
     * @param  'month'|'year'  $interval
     * @return array{price_id: string, product_id: string, created: bool, deactivated: list<string>}
     */
    public function ensure(string $priceKey, string $planSlug, string $name, int $grossCents, string $currency, string $interval): array
    {
        $productId = $this->ensureProduct($planSlug, $name);
        $existing = $this->find($priceKey, $grossCents);

        if ($existing !== null) {
            return [
                'price_id' => $existing,
                'product_id' => $productId,
                'created' => false,
                'deactivated' => $this->deactivateOthers($productId, $priceKey, $existing),
            ];
        }

        $price = $this->stripe->prices->create([
            'product' => $productId,
            'unit_amount' => $grossCents,
            'currency' => strtolower($currency),
            'recurring' => ['interval' => $interval, 'interval_count' => 1],
            'tax_behavior' => 'inclusive',
            'lookup_key' => self::lookupKey($priceKey, $grossCents),
            'nickname' => $name,
            'metadata' => ['sun_price_key' => $priceKey],
        ]);

        return [
            'price_id' => $price->id,
            'product_id' => $productId,
            'created' => true,
            'deactivated' => $this->deactivateOthers($productId, $priceKey, $price->id),
        ];
    }

    /**
     * Deaktiviert alle anderen aktiven Prices desselben Preis-Keys am Product.
     *
     * @return list<string> IDs der deaktivierten Prices
     */
    private function deactivateOthers(string $productId, string $priceKey, string $keepPriceId): array
    {
        $deactivated = [];
        $prices = $this->stripe->prices->all(['product' => $productId, 'active' => true, 'limit' => 100]);

        foreach ($prices->autoPagingIterator() as $price) {
            if ($price->id === $keepPriceId || ($price->metadata['sun_price_key'] ?? null) !== $priceKey) {
                continue;
            }

            $this->stripe->prices->update($price->id, ['active' => false]);
            $deactivated[] = $price->id;
        }

        return $deactivated;
    }

    private function ensureProduct(string $planSlug, string $name): string
    {
        $id = self::productId($planSlug);

        try {
            $product = $this->stripe->products->retrieve($id);

            if (! $product->active) {
                $this->stripe->products->update($id, ['active' => true]);
            }

            return $id;
        } catch (\Stripe\Exception\InvalidRequestException) {
            return $this->stripe->products->create([
                'id' => $id,
                'name' => $name,
                'metadata' => ['sun_plan_slug' => $planSlug],
            ])->id;
        }
    }
}
