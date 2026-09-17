<?php

declare(strict_types=1);

namespace Tests\Feature\Webhooks;

use App\Events\Company\CompanyInquiryReceived;
use App\Models\Portal\CompanyInquiry;
use App\Models\Tenant;
use App\Providers\TenancyServiceProvider;
use App\Services\Leads\LeadWebhookSignature;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Schema;
use Illuminate\Testing\TestResponse;
use Stancl\Tenancy\Contracts\Tenant as TenantContract;
use Stancl\Tenancy\Middleware\PreventAccessFromCentralDomains;
use Tests\TestCase;

/**
 * #37: vom signierten Webhook bis zum Datensatz in company_inquiries.
 *
 * Die Queue laeuft synchron (phpunit.xml), die Tenant-Verbindung auf SQLite im
 * Speicher wie in StoreCompanyInquiryTest; keine echte Datenbank wird angefasst.
 */
class LeadWebhookStoresInquiryTest extends TestCase
{
    private const SECRET = 'test-secret';

    private const TOKEN = 'funnel-token-a';

    private const LEAD_UUID = '5f0c2a8e-1d4b-4c55-8a3e-7b9d0e6f1a22';

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'cache.default' => 'array',
            'queue.default' => 'sync',
            'database.connections.tenant' => [
                'driver' => 'sqlite',
                'database' => ':memory:',
                'prefix' => '',
                'foreign_key_constraints' => true,
            ],
        ]);
        DB::purge('tenant');

        Schema::connection('tenant')->create('companies', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('name');
            $table->string('slug');
            $table->timestamps();
        });

        DB::connection('tenant')->table('companies')->insert([
            'id' => 42, 'user_id' => null, 'name' => 'Elektro Muster', 'slug' => 'elektro-muster',
        ]);

        $migration = require database_path('migrations/tenant/2026_09_17_000002_create_company_inquiries_table.php');
        $default = config('database.default');
        config(['database.default' => 'tenant']);
        $migration->up();
        config(['database.default' => $default]);

        Event::fake([CompanyInquiryReceived::class]);
        $this->withoutMiddleware([TenancyServiceProvider::TENANCY_INITIALIZER, PreventAccessFromCentralDomains::class]);

        $tenant = new Tenant;
        $tenant->uuid = 'tenant-a';
        $tenant->setAttribute(Tenant::LEAD_FUNNEL_TOKEN, self::TOKEN);
        $tenant->setAttribute(Tenant::LEAD_WEBHOOK_SECRET, self::SECRET);
        $this->app->instance(TenantContract::class, $tenant);
    }

    public function test_signed_webhook_creates_inquiry_once(): void
    {
        $this->deliver('env-1')->assertStatus(202);
        $this->deliver('env-2')->assertStatus(202)->assertJson(['status' => 'duplicate']);

        $inquiry = CompanyInquiry::query()->sole();

        $this->assertSame(42, (int) $inquiry->company_id);
        $this->assertSame(self::LEAD_UUID, $inquiry->lead_uuid);
        $this->assertSame('Mara Lindqvist', $inquiry->contact_name);
        $this->assertSame('mara@example.com', $inquiry->contact_email);
        $this->assertSame(['leistung'], array_column($inquiry->answers, 'key'));
        Event::assertDispatchedTimes(CompanyInquiryReceived::class, 1);
    }

    public function test_signed_webhook_with_profile_url_as_text_creates_inquiry(): void
    {
        // #38: Format aus Produktion, firmenprofil ist die Profil-URL.
        $this->deliver('env-3', 'https://elektrikerportal.com/42-elektro-muster')->assertStatus(202);

        $this->assertSame(42, (int) CompanyInquiry::query()->sole()->company_id);
        Event::assertDispatchedTimes(CompanyInquiryReceived::class, 1);
    }

    /**
     * @param  array<string, mixed>|string  $firmenprofil
     */
    private function deliver(string $envelopeId, array|string $firmenprofil = ['id' => 42, 'slug' => 'elektro-muster']): TestResponse
    {
        $body = (string) json_encode([
            'id' => $envelopeId,
            'event' => 'lead.created',
            'occurred_at' => now()->toIso8601String(),
            'funnel' => ['public_token' => self::TOKEN, 'name' => 'Elektriker-Anfrage'],
            'data' => [
                'lead' => [
                    'id' => 881,
                    'uuid' => self::LEAD_UUID,
                    'state' => 'neu',
                    'score' => 42,
                    'result_key' => null,
                    'created_at' => '2026-09-17T10:30:00+02:00',
                    'contact' => ['name' => 'Mara Lindqvist', 'email' => 'mara@example.com', 'phone' => '0170 1234567'],
                    'answers' => [
                        ['field_key' => 'leistung', 'label' => 'Was soll gemacht werden?', 'value' => 'wallbox', 'value_label' => 'Wallbox'],
                        ['field_key' => 'firmenprofil', 'label' => 'firmenprofil', 'value' => $firmenprofil, 'value_label' => null],
                    ],
                ],
            ],
        ]);
        $timestamp = time();

        return $this->call('POST', '/webhooks/leads', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_X_FUNNEL_SIGNATURE' => (new LeadWebhookSignature)->sign(self::SECRET, $body, $timestamp),
            'HTTP_X_FUNNEL_TIMESTAMP' => (string) $timestamp,
            'HTTP_X_FUNNEL_EVENT' => 'lead.created',
        ], $body);
    }
}
