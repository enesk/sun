<?php

declare(strict_types=1);

namespace App\Guide\Llm;

use App\Guide\Models\Topic;
use App\Guide\Models\TopicRun;

/**
 * Wozu ein Aufruf des Ratgebersystems gehoert (#5).
 *
 * Bestimmt, gegen welche Budgets geprueft wird (Tenant, Lauf) und mit welchem
 * Bezug der Aufruf in llm_usage_logs landet. Ohne Angabe wird der Tenant aus
 * dem laufenden Tenancy-Kontext uebernommen.
 */
final class LlmCallContext
{
    public const REFERENCE_RUN = 'guide_run';

    public function __construct(
        public readonly ?int $tenantId = null,
        public readonly ?int $topicId = null,
        public readonly ?int $runId = null,
    ) {}

    public static function current(): self
    {
        return new self(tenantId: self::currentTenantId());
    }

    public static function forRun(TopicRun $run): self
    {
        return new self(
            tenantId: self::currentTenantId(),
            topicId: $run->guide_topic_id === null ? null : (int) $run->guide_topic_id,
            runId: (int) $run->getKey(),
        );
    }

    public static function forTopic(Topic $topic): self
    {
        return new self(tenantId: self::currentTenantId(), topicId: (int) $topic->getKey());
    }

    public function withTenantId(?int $tenantId): self
    {
        return new self($tenantId, $this->topicId, $this->runId);
    }

    private static function currentTenantId(): ?int
    {
        if (! tenancy()->initialized) {
            return null;
        }

        $tenant = tenant();

        return $tenant === null ? null : (int) $tenant->getKey();
    }
}
