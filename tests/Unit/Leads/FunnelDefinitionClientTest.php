<?php

declare(strict_types=1);

namespace Tests\Unit\Leads;

use App\Services\Leads\FunnelDefinitionClient;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

/**
 * #25: Funnel-Snapshot laden, je Portal cachen, bei Ausfall letzter guter Stand.
 */
class FunnelDefinitionClientTest extends TestCase
{
    private const API = 'https://leads.test/api/public/v1';

    protected function setUp(): void
    {
        parent::setUp();

        config(['cache.default' => 'array', 'leads.api_url' => self::API]);
        Cache::flush();
    }

    public function test_loads_snapshot_and_serves_it_from_cache(): void
    {
        Http::fake([self::API.'/funnels/tok-a' => Http::response(['data' => $this->snapshot()])]);

        $client = new FunnelDefinitionClient;
        $definition = $client->get('tok-a', 'tenant-a');

        $this->assertNotNull($definition);
        $this->assertSame(3, $definition->version);
        $this->assertSame('Elektrikerportal', $definition->name);
        $this->assertSame([1, 2], array_map(fn ($s) => $s->position, $definition->steps));

        $first = $definition->firstStep();
        $this->assertSame(['leistung', 'firmenprofil'], array_map(fn ($q) => $q->key, $first->questions));
        $this->assertSame(['leistung'], array_map(fn ($q) => $q->key, $first->visibleQuestions()));
        $this->assertSame(['reparatur', 'wallbox'], array_map(fn ($o) => $o->value, $first->questions[0]->options));
        $this->assertTrue($first->questions[1]->systemProvided);
        $this->assertCount(1, $definition->systemQuestions());

        $client->get('tok-a', 'tenant-a');
        Http::assertSentCount(1);
    }

    public function test_timeout_serves_last_good_snapshot(): void
    {
        Http::fakeSequence(self::API.'/funnels/tok-a')
            ->push(['data' => $this->snapshot()])
            ->pushFailedConnection('cURL error 28: Operation timed out');

        $client = new FunnelDefinitionClient;
        $this->assertSame(3, $client->get('tok-a', 'tenant-a')?->version);

        // Frischen Eintrag ablaufen lassen, der letzte gute Stand bleibt.
        $this->travel(2)->hours();
        Log::spy();

        $definition = $client->get('tok-a', 'tenant-a');

        $this->assertNotNull($definition);
        $this->assertSame(3, $definition->version);
        Log::shouldHaveReceived('warning')->withArgs(fn (string $message) => str_contains($message, 'nicht geladen'))->once();

        // Waehrend der Sperrfrist wird nicht erneut gefragt.
        $client->get('tok-a', 'tenant-a');
        Http::assertSentCount(2);
    }

    public function test_not_found_gives_no_dialog_and_drops_last_good(): void
    {
        Http::fakeSequence(self::API.'/funnels/tok-a')
            ->push(['data' => $this->snapshot()])
            ->push(['message' => 'Not Found'], 404)
            ->pushFailedConnection();

        $client = new FunnelDefinitionClient;
        $this->assertNotNull($client->get('tok-a', 'tenant-a'));

        $this->travel(2)->hours();
        $this->assertNull($client->get('tok-a', 'tenant-a'));

        // Ein spaeterer Ausfall holt den zurueckgezogenen Funnel nicht zurueck.
        $this->travel(10)->minutes();
        $this->assertNull($client->get('tok-a', 'tenant-a'));
    }

    public function test_unknown_question_type_is_logged_and_skipped(): void
    {
        $snapshot = $this->snapshot();
        // steps[1] ist Position 1, also der erste Schritt nach dem Sortieren.
        $snapshot['steps'][1]['questions'][] = [
            'field_key' => 'unterschrift',
            'type' => 'signature',
            'label' => 'Unterschrift',
            'position' => 3,
        ];

        Http::fake([self::API.'/*' => Http::response(['data' => $snapshot])]);
        Log::spy();

        $definition = (new FunnelDefinitionClient)->get('tok-a', 'tenant-a');

        $this->assertNotNull($definition);
        $this->assertSame(['leistung', 'firmenprofil'], array_map(fn ($q) => $q->key, $definition->firstStep()->questions));
        Log::shouldHaveReceived('warning')
            ->withArgs(fn (string $message, array $context) => ($context['type'] ?? null) === 'signature'
                && ($context['field_key'] ?? null) === 'unterschrift')
            ->once();
    }

    public function test_cache_is_separated_per_tenant(): void
    {
        $other = $this->snapshot();
        $other['version'] = 7;
        $other['funnel']['name'] = 'Tierarztportal';

        Http::fake(function (Request $request) use ($other) {
            return str_ends_with($request->url(), '/funnels/tok-b')
                ? Http::response(['data' => $other])
                : Http::response(['data' => $this->snapshot()]);
        });

        $client = new FunnelDefinitionClient;

        $this->assertSame('Elektrikerportal', $client->get('tok-a', 'tenant-a')?->name);
        $this->assertSame('Tierarztportal', $client->get('tok-b', 'tenant-b')?->name);

        // Gleicher Token, anderes Portal: eigener Eintrag, eigener Abruf.
        $client->get('tok-a', 'tenant-b');
        Http::assertSentCount(3);

        // Leeren fuer ein Portal laesst das andere unberuehrt.
        $client->forget('tok-a', 'tenant-a');
        $client->get('tok-a', 'tenant-b');
        Http::assertSentCount(3);
    }

    /**
     * @return array<string, mixed>
     */
    private function snapshot(): array
    {
        return [
            'funnel' => ['name' => 'Elektrikerportal', 'contact_step_position' => 2],
            'version' => 3,
            'steps' => [
                [
                    'position' => 2,
                    'title' => 'Kontakt',
                    'questions' => [
                        ['field_key' => 'telefon', 'type' => 'phone', 'label' => 'Telefon', 'required' => true, 'position' => 1],
                    ],
                ],
                [
                    'position' => 1,
                    'title' => 'Was brauchst du?',
                    'questions' => [
                        ['field_key' => 'firmenprofil', 'type' => 'text', 'label' => 'Firmenprofil', 'position' => 2],
                        [
                            'field_key' => 'leistung',
                            'type' => 'single_choice',
                            'label' => 'Leistung',
                            'required' => true,
                            'position' => 1,
                            'validation' => [],
                            'meta' => [],
                            'options' => [
                                ['value' => 'wallbox', 'label' => 'Wallbox', 'position' => 2],
                                ['value' => 'reparatur', 'label' => 'Reparatur', 'position' => 1],
                            ],
                        ],
                    ],
                ],
            ],
            'conditions' => [],
            'results' => [],
            'theme' => null,
        ];
    }
}
