<?php

declare(strict_types=1);

namespace Tests\Feature\Webhooks;

use App\Jobs\Leads\StoreCompanyInquiry;
use App\Models\Tenant;
use App\Providers\TenancyServiceProvider;
use App\Services\Leads\LeadWebhookSignature;
use Illuminate\Support\Facades\Queue;
use Stancl\Tenancy\Contracts\Tenant as TenantContract;
use Stancl\Tenancy\Middleware\PreventAccessFromCentralDomains;
use Tests\TestCase;

/**
 * #31: Empfang des Leadsystem-Webhooks. Ohne Datenbank: der Tenant wird als
 * ungespeichertes Model gebunden, die Tenancy-Middleware ist abgeschaltet.
 */
class LeadWebhookTest extends TestCase
{
    private const SECRET = 'test-secret';

    private const TOKEN = 'funnel-token-a';

    protected function setUp(): void
    {
        parent::setUp();

        config(['cache.default' => 'array']);
        Queue::fake();
        $this->withoutMiddleware([TenancyServiceProvider::TENANCY_INITIALIZER, PreventAccessFromCentralDomains::class]);

        $tenant = new Tenant;
        $tenant->uuid = 'tenant-a';
        $tenant->setAttribute(Tenant::LEAD_FUNNEL_TOKEN, self::TOKEN);
        $tenant->setAttribute(Tenant::LEAD_WEBHOOK_SECRET, self::SECRET);
        $this->app->instance(TenantContract::class, $tenant);
    }

    public function test_valid_signature_is_accepted_and_queued(): void
    {
        $this->deliver($this->envelope())->assertStatus(202)->assertJson(['status' => 'accepted']);

        Queue::assertPushed(StoreCompanyInquiry::class, fn (StoreCompanyInquiry $job): bool => data_get($job->envelope, 'data.lead.uuid') === 'lead-1');
    }

    public function test_wrong_signature_is_rejected(): void
    {
        $this->deliver($this->envelope(), secret: 'falsch')->assertStatus(401);

        Queue::assertNothingPushed();
    }

    public function test_old_timestamp_is_rejected(): void
    {
        $this->deliver($this->envelope(), timestamp: time() - LeadWebhookSignature::TOLERANCE - 1)->assertStatus(401);

        Queue::assertNothingPushed();
    }

    public function test_foreign_funnel_token_is_rejected(): void
    {
        $this->deliver($this->envelope(token: 'fremder-funnel'))->assertStatus(403);

        Queue::assertNothingPushed();
    }

    public function test_duplicate_delivery_is_stored_once(): void
    {
        $this->deliver($this->envelope(envelopeId: 'env-1'))->assertStatus(202);
        $this->deliver($this->envelope(envelopeId: 'env-2'))->assertStatus(202)->assertJson(['status' => 'duplicate']);

        Queue::assertPushed(StoreCompanyInquiry::class, 1);
    }

    public function test_other_event_is_ignored(): void
    {
        $this->deliver($this->envelope(event: 'lead.purchased'))->assertNoContent();

        Queue::assertNothingPushed();
    }

    /**
     * @return array<string, mixed>
     */
    private function envelope(string $event = 'lead.created', string $token = self::TOKEN, string $envelopeId = 'env-1'): array
    {
        return [
            'id' => $envelopeId,
            'event' => $event,
            'occurred_at' => now()->toIso8601String(),
            'funnel' => ['public_token' => $token, 'name' => 'Elektriker-Anfrage'],
            'data' => [
                'lead' => [
                    'id' => 17,
                    'uuid' => 'lead-1',
                    'contact' => ['name' => 'Max Muster', 'email' => 'max@example.test'],
                    'answers' => ['firmenprofil' => ['id' => 42, 'slug' => 'elektro-muster']],
                ],
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $envelope
     */
    private function deliver(array $envelope, string $secret = self::SECRET, ?int $timestamp = null): \Illuminate\Testing\TestResponse
    {
        $body = (string) json_encode($envelope);
        $timestamp ??= time();

        return $this->call('POST', '/webhooks/leads', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_X_FUNNEL_SIGNATURE' => (new LeadWebhookSignature)->sign($secret, $body, $timestamp),
            'HTTP_X_FUNNEL_TIMESTAMP' => (string) $timestamp,
            'HTTP_X_FUNNEL_EVENT' => $envelope['event'],
        ], $body);
    }
}
