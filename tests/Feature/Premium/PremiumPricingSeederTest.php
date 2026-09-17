<?php

namespace Tests\Feature\Premium;

use App\Models\PlanPrice;
use App\Models\PlanPricePaymentProviderData;
use App\Models\Tenant;
use App\Services\Premium\StripePremiumPriceProvisioner;
use App\Support\Tenancy\TenantPremiumPricing;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Stripe\StripeClient;
use Tests\TestCase;

/**
 * #38: Die zentralen Tabellen liegen hier in einer SQLite-Datei, Stripe ist
 * durch einen Provisioner im Speicher ersetzt. Keine echte Datenbank, keine
 * Anfrage an Stripe.
 */
class PremiumPricingSeederTest extends TestCase
{
    private string $database;

    private InMemoryPriceProvisioner $provisioner;

    protected function setUp(): void
    {
        parent::setUp();

        $this->database = (string) tempnam(sys_get_temp_dir(), 'premium-pricing-');
        $sqlite = ['driver' => 'sqlite', 'database' => $this->database, 'prefix' => '', 'foreign_key_constraints' => false];

        config([
            'database.connections.central' => $sqlite,
            'database.connections.mysql' => $sqlite,
            'database.default' => 'central',
            'tenancy.database.central_connection' => 'central',
            'services.stripe.secret_key' => 'sk_test_fake',
        ]);
        DB::purge('central');
        DB::purge('mysql');

        $this->createTables();

        DB::table('currencies')->insert(['id' => 1, 'code' => 'EUR', 'name' => 'Euro', 'symbol' => '€']);
        DB::table('payment_providers')->insert(['id' => 1, 'slug' => 'stripe', 'name' => 'Stripe', 'is_active' => true]);

        foreach (array_values((array) config('premium.price_plans')) as $i => $slug) {
            DB::table('plans')->insert(['id' => $i + 1, 'slug' => $slug, 'name' => $slug]);
        }

        // Alt-Stand wie lokal: Premium monatlich mit Platzhalterbetrag und fremder Zuordnung.
        DB::table('plan_prices')->insert(['id' => 1, 'plan_id' => 3, 'currency_id' => 1, 'price' => 990, 'type' => 'flat_rate']);
        DB::table('plan_price_payment_provider_data')->insert([
            'plan_price_id' => 1, 'payment_provider_id' => 1, 'payment_provider_price_id' => 'price_alt', 'type' => 'main_price',
        ]);

        DB::table('tenants')->insert([
            ['id' => 19, 'uuid' => 'uuid-elektriker', 'name' => 'Elektriker', 'domain' => 'elektriker.test', 'data' => json_encode(['theme.active' => 'sun-v2'])],
            ['id' => 20, 'uuid' => 'uuid-alt', 'name' => 'Altportal', 'domain' => 'alt.test', 'data' => json_encode(['theme.active' => 'default'])],
        ]);

        $this->provisioner = new InMemoryPriceProvisioner;
        $this->app->instance(StripePremiumPriceProvisioner::class, $this->provisioner);
    }

    protected function tearDown(): void
    {
        @unlink($this->database);

        parent::tearDown();
    }

    public function test_sets_prices_and_enables_sale_for_sun_v2_portals(): void
    {
        $this->artisan('premium:pricing:seed')->assertSuccessful();

        $pricing = TenantPremiumPricing::for(Tenant::find(19));

        $this->assertTrue($pricing->isSaleEnabled());
        $this->assertTrue($pricing->isFeaturedSaleEnabled());
        $this->assertSame(4900, $pricing->grossCents('pro_monthly'));
        $this->assertSame(79000, $pricing->grossCents('premium_yearly'));
        $this->assertSame('price_sun_premium_monthly_7900', $pricing->stripePriceId('premium_monthly'));

        // Nicht-sun-v2-Portal bleibt ohne Angabe von --tenant unberuehrt.
        $this->assertFalse(TenantPremiumPricing::for(Tenant::find(20))->isSaleEnabled());

        // Checkout rechnet ueber PlanPrice: Betrag und Stripe-Zuordnung zeigen auf denselben Price.
        $planPrice = PlanPrice::where('plan_id', 3)->sole();
        $this->assertSame(7900, (int) $planPrice->price);
        $this->assertSame(
            ['price_sun_premium_monthly_7900'],
            PlanPricePaymentProviderData::where('plan_price_id', $planPrice->id)->pluck('payment_provider_price_id')->all(),
        );
        $this->assertSame('sun-premium-monthly', DB::table('plan_payment_provider_data')->where('plan_id', 3)->value('payment_provider_product_id'));
    }

    public function test_second_run_creates_no_duplicates(): void
    {
        $this->artisan('premium:pricing:seed')->assertSuccessful();
        $this->artisan('premium:pricing:seed')->assertSuccessful();

        $this->assertSame(5, $this->provisioner->created);
        $this->assertSame(5, PlanPrice::count());
        $this->assertSame(5, PlanPricePaymentProviderData::count());
        $this->assertSame(5, DB::table('plan_payment_provider_data')->count());
    }

    public function test_aborts_with_live_key(): void
    {
        config(['services.stripe.secret_key' => 'sk_live_fake']);

        $this->artisan('premium:pricing:seed')->assertFailed();

        $this->assertSame(0, $this->provisioner->created);
        $this->assertFalse(TenantPremiumPricing::for(Tenant::find(19))->isSaleEnabled());
    }

    public function test_live_key_only_with_live_option(): void
    {
        config(['services.stripe.secret_key' => 'sk_live_fake']);

        $this->artisan('premium:pricing:seed', ['--tenant' => ['elektriker.test'], '--live' => true])->assertSuccessful();

        $this->assertTrue(TenantPremiumPricing::for(Tenant::find(19))->isSaleEnabled());
    }

    public function test_without_key_writes_amounts_only(): void
    {
        config(['services.stripe.secret_key' => null]);

        $this->artisan('premium:pricing:seed', ['--tenant' => ['19']])->assertSuccessful();

        $pricing = TenantPremiumPricing::for(Tenant::find(19));

        $this->assertSame(4900, $pricing->grossCents('pro_monthly'));
        $this->assertNull($pricing->stripePriceId('pro_monthly'));
        $this->assertFalse($pricing->isSaleEnabled());
        $this->assertSame(0, $this->provisioner->created);
    }

    public function test_without_key_drops_price_id_of_changed_amount(): void
    {
        DB::table('tenants')->where('id', 19)->update(['data' => json_encode([
            'theme.active' => 'sun-v2',
            TenantPremiumPricing::ATTRIBUTE => [
                'pro_monthly_gross_cents' => 2900, 'pro_monthly_stripe_price_id' => 'price_alt_2900',
                'featured_monthly_gross_cents' => 3900, 'featured_monthly_stripe_price_id' => 'price_featured',
            ],
        ])]);
        config(['services.stripe.secret_key' => null]);

        $this->artisan('premium:pricing:seed', ['--tenant' => ['19']])->assertSuccessful();

        $pricing = TenantPremiumPricing::for(Tenant::find(19));

        $this->assertNull($pricing->stripePriceId('pro_monthly'));
        $this->assertSame('price_featured', $pricing->stripePriceId('featured_monthly'));
    }

    public function test_dry_run_writes_nothing(): void
    {
        $this->artisan('premium:pricing:seed', ['--dry-run' => true])->assertSuccessful();

        $this->assertSame(0, $this->provisioner->created);
        $this->assertNull(Tenant::find(19)->getAttribute(TenantPremiumPricing::ATTRIBUTE));
        $this->assertSame(990, (int) PlanPrice::where('plan_id', 3)->value('price'));
        $this->assertSame('price_alt', PlanPricePaymentProviderData::value('payment_provider_price_id'));
    }

    private function createTables(): void
    {
        Schema::create('tenants', function (Blueprint $table) {
            $table->id();
            $table->string('uuid');
            $table->string('name');
            $table->string('domain')->nullable();
            $table->boolean('is_name_auto_generated')->default(false);
            $table->unsignedBigInteger('created_by')->nullable();
            $table->json('data')->nullable();
            $table->timestamps();
        });
        Schema::create('currencies', function (Blueprint $table) {
            $table->id();
            $table->string('code');
            $table->string('name');
            $table->string('symbol');
            $table->timestamps();
        });
        Schema::create('payment_providers', function (Blueprint $table) {
            $table->id();
            $table->string('slug');
            $table->string('name');
            $table->boolean('is_active');
            $table->timestamps();
        });
        Schema::create('plans', function (Blueprint $table) {
            $table->id();
            $table->string('slug');
            $table->string('name');
            $table->timestamps();
        });
        Schema::create('plan_prices', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('plan_id');
            $table->unsignedBigInteger('currency_id');
            $table->integer('price');
            $table->integer('price_per_unit')->nullable();
            $table->string('type')->nullable();
            $table->json('tiers')->nullable();
            $table->timestamps();
        });
        Schema::create('plan_payment_provider_data', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('plan_id');
            $table->unsignedBigInteger('payment_provider_id');
            $table->string('payment_provider_product_id');
            $table->timestamps();
        });
        Schema::create('plan_price_payment_provider_data', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('plan_price_id');
            $table->unsignedBigInteger('payment_provider_id');
            $table->string('payment_provider_price_id');
            $table->string('type');
            $table->timestamps();
        });
    }
}

class InMemoryPriceProvisioner extends StripePremiumPriceProvisioner
{
    public int $created = 0;

    /** @var array<string, string> */
    private array $prices = [];

    public function __construct()
    {
        parent::__construct(new StripeClient('sk_test_fake'));
    }

    public function find(string $priceKey, int $grossCents): ?string
    {
        return $this->prices[self::lookupKey($priceKey, $grossCents)] ?? null;
    }

    public function ensure(string $priceKey, string $planSlug, string $name, int $grossCents, string $currency, string $interval): array
    {
        $lookupKey = self::lookupKey($priceKey, $grossCents);
        $created = ! isset($this->prices[$lookupKey]);

        if ($created) {
            $this->prices[$lookupKey] = "price_{$lookupKey}";
            $this->created++;
        }

        return ['price_id' => $this->prices[$lookupKey], 'product_id' => self::productId($planSlug), 'created' => $created, 'deactivated' => []];
    }
}
