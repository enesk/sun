<?php

declare(strict_types=1);

namespace App\Content\Sources\Connectors;

use App\Content\Enums\SourceFrequency;
use App\Content\Sources\Contracts\SourceConnector;
use App\Content\Sources\SourceItemDto;
use App\Content\Sources\TenantContext;
use Illuminate\Support\Collection;

/**
 * Beispiel-Connector fuer die Abnahme des Frameworks (#7).
 *
 * Er ruft nichts Externes ab und erzeugt je Lauf ein Rohsignal aus dem
 * ersten Branchen-Keyword des Mandanten. Damit laesst sich auf Staging
 * belegen, dass Scheduler, Queue, Tenant-Kontext und Speicherung
 * zusammenspielen. Aktivierung ueber CONTENT_SOURCES_DUMMY_ENABLED.
 */
class DummyPingConnector implements SourceConnector
{
    public function key(): string
    {
        return 'dummy_ping';
    }

    public function schedule(): string
    {
        return SourceFrequency::HOURLY->value;
    }

    public function fetch(TenantContext $context): Collection
    {
        $keywords = $context->branchKeywords();
        $keyword = $keywords[0] ?? $context->slug();
        $stamp = now()->format('Y-m-d H:00');

        return collect([
            new SourceItemDto(
                type: 'internal',
                title: "Testsignal {$keyword} ({$stamp})",
                url: $context->baseUrl().'/#dummy-ping-'.now()->format('YmdH'),
                snippet: "Selbsttest des Quell-Frameworks fuer {$context->name}. Kein redaktionelles Signal.",
                regionScope: 'national',
                keywords: array_slice($keywords, 0, 5),
                signalStrength: 0.01,
                publishedAt: now(),
                raw: ['generated_at' => now()->toIso8601String(), 'tenant_id' => $context->tenantId],
                externalId: 'dummy-'.now()->format('YmdH'),
            ),
        ]);
    }
}
