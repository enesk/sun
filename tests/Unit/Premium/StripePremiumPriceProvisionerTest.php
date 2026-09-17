<?php

namespace Tests\Unit\Premium;

use App\Services\Premium\StripePremiumPriceProvisioner;
use PHPUnit\Framework\TestCase;
use Stripe\ApiRequestor;
use Stripe\HttpClient\ClientInterface;
use Stripe\StripeClient;

/**
 * #38: Stripe wird ueber einen austauschbaren HTTP-Client simuliert, es geht
 * keine Anfrage nach draussen.
 */
class StripePremiumPriceProvisionerTest extends TestCase
{
    private FakeStripeHttpClient $http;

    protected function setUp(): void
    {
        parent::setUp();

        $this->http = new FakeStripeHttpClient;
        ApiRequestor::setHttpClient($this->http);
    }

    protected function tearDown(): void
    {
        ApiRequestor::setHttpClient(null);

        parent::tearDown();
    }

    public function test_creates_product_and_inclusive_price_with_lookup_key(): void
    {
        $result = $this->provisioner()->ensure('pro_monthly', 'pro-monthly', 'Pro monatlich', 2900, 'EUR', 'month');

        $this->assertTrue($result['created']);
        $this->assertSame('sun-pro-monthly', $result['product_id']);
        $this->assertCount(1, $this->http->prices);

        $price = $this->http->prices[0];
        $this->assertSame($result['price_id'], $price['id']);
        $this->assertSame('sun_pro_monthly_2900', $price['lookup_key']);
        $this->assertSame('inclusive', $price['tax_behavior']);
        $this->assertSame('2900', (string) $price['unit_amount']);
        $this->assertSame('eur', $price['currency']);
        $this->assertSame('month', $price['recurring']['interval']);
        $this->assertSame('sun-pro-monthly', $price['product']);
    }

    public function test_second_run_reuses_existing_price(): void
    {
        $first = $this->provisioner()->ensure('pro_yearly', 'pro-yearly', 'Pro jährlich', 29000, 'EUR', 'year');
        $second = $this->provisioner()->ensure('pro_yearly', 'pro-yearly', 'Pro jährlich', 29000, 'EUR', 'year');

        $this->assertFalse($second['created']);
        $this->assertSame($first['price_id'], $second['price_id']);
        $this->assertCount(1, $this->http->prices);
        $this->assertCount(1, $this->http->products);
    }

    public function test_changed_amount_creates_new_price_and_deactivates_old_one(): void
    {
        $old = $this->provisioner()->ensure('pro_monthly', 'pro-monthly', 'Pro monatlich', 2900, 'EUR', 'month');
        $new = $this->provisioner()->ensure('pro_monthly', 'pro-monthly', 'Pro monatlich', 4900, 'EUR', 'month');

        $this->assertNotSame($old['price_id'], $new['price_id']);
        $this->assertSame([$old['price_id']], $new['deactivated']);
        $this->assertCount(2, $this->http->prices);
        $this->assertCount(1, $this->http->products);
        $this->assertFalse($this->http->prices[0]['active']);
        $this->assertTrue($this->http->prices[1]['active']);
    }

    public function test_foreign_prices_on_product_stay_active(): void
    {
        $this->provisioner()->ensure('pro_monthly', 'pro-monthly', 'Pro monatlich', 2900, 'EUR', 'month');
        $this->http->prices[] = ['object' => 'price', 'id' => 'price_fremd', 'active' => true, 'product' => 'sun-pro-monthly', 'lookup_key' => null, 'metadata' => []];

        $result = $this->provisioner()->ensure('pro_monthly', 'pro-monthly', 'Pro monatlich', 4900, 'EUR', 'month');

        $this->assertCount(1, $result['deactivated']);
        $this->assertTrue($this->http->prices[1]['active']);
    }

    public function test_find_returns_null_without_price(): void
    {
        $this->assertNull($this->provisioner()->find('premium_monthly', 5900));
        $this->assertSame([], $this->http->prices);
    }

    private function provisioner(): StripePremiumPriceProvisioner
    {
        return new StripePremiumPriceProvisioner(new StripeClient('sk_test_fake'));
    }
}

/**
 * Minimaler Stripe-Nachbau fuer products (retrieve/create) und prices (list/create).
 */
class FakeStripeHttpClient implements ClientInterface
{
    /** @var list<array<string, mixed>> */
    public array $products = [];

    /** @var list<array<string, mixed>> */
    public array $prices = [];

    public function request($method, $absUrl, $headers, $params, $hasFile, $apiMode = 'v1', $maxNetworkRetries = null)
    {
        $path = (string) parse_url($absUrl, PHP_URL_PATH);
        parse_str((string) parse_url($absUrl, PHP_URL_QUERY), $query);
        $params = array_merge($query, (array) $params);

        if ($method === 'get' && preg_match('#^/v1/products/(.+)$#', $path, $m) === 1) {
            foreach ($this->products as $product) {
                if ($product['id'] === urldecode($m[1])) {
                    return $this->json($product);
                }
            }

            return $this->json(['error' => ['type' => 'invalid_request_error', 'code' => 'resource_missing', 'message' => 'No such product']], 404);
        }

        if ($method === 'post' && $path === '/v1/products') {
            $product = ['object' => 'product', 'active' => true] + $params;
            $this->products[] = $product;

            return $this->json($product);
        }

        if ($method === 'get' && $path === '/v1/prices') {
            $keys = (array) ($params['lookup_keys'] ?? []);
            $product = $params['product'] ?? null;
            $data = array_values(array_filter($this->prices, fn (array $p): bool => $p['active']
                && ($keys === [] || in_array($p['lookup_key'], $keys, true))
                && ($product === null || $p['product'] === $product)));

            return $this->json(['object' => 'list', 'data' => $data, 'has_more' => false, 'url' => '/v1/prices']);
        }

        if ($method === 'post' && preg_match('#^/v1/prices/(.+)$#', $path, $m) === 1) {
            foreach ($this->prices as $i => $price) {
                if ($price['id'] === urldecode($m[1])) {
                    $this->prices[$i]['active'] = filter_var($params['active'] ?? true, FILTER_VALIDATE_BOOLEAN);

                    return $this->json($this->prices[$i]);
                }
            }

            return $this->json(['error' => ['type' => 'invalid_request_error', 'message' => 'No such price']], 404);
        }

        if ($method === 'post' && $path === '/v1/prices') {
            $price = ['object' => 'price', 'id' => 'price_fake_'.(count($this->prices) + 1), 'active' => true] + $params;
            $this->prices[] = $price;

            return $this->json($price);
        }

        return $this->json(['error' => ['type' => 'invalid_request_error', 'message' => "Unerwartet: {$method} {$path}"]], 400);
    }

    /**
     * @param  array<string, mixed>  $body
     * @return array{0: string, 1: int, 2: array<string, string>}
     */
    private function json(array $body, int $status = 200): array
    {
        return [(string) json_encode($body), $status, ['request-id' => 'req_fake']];
    }
}
