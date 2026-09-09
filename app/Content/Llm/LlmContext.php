<?php

declare(strict_types=1);

namespace App\Content\Llm;

use App\Content\Models\ArticleDraft;

/**
 * Wozu ein Provider-Aufruf gehoert (#6).
 *
 * Bestimmt, gegen welches Budget geprueft wird und mit welchem Bezug der
 * Aufruf in llm_usage_logs landet. Ohne Angabe wird der Mandant aus dem
 * laufenden Tenancy-Kontext uebernommen.
 */
final class LlmContext
{
    public function __construct(
        public readonly ?int $tenantId = null,
        public readonly ?string $referenceType = null,
        public readonly ?int $referenceId = null,
        public readonly ?string $operation = null,
    ) {}

    /**
     * Mandant aus dem laufenden Tenancy-Kontext, ohne Bezugsobjekt.
     */
    public static function current(?string $operation = null): self
    {
        return new self(tenantId: self::currentTenantId(), operation: $operation);
    }

    public static function forDraft(ArticleDraft $draft, ?string $operation = null): self
    {
        return new self(
            tenantId: self::currentTenantId(),
            referenceType: $draft->getTable(),
            referenceId: (int) $draft->getKey(),
            operation: $operation,
        );
    }

    public static function forDraftId(int $draftId, ?int $tenantId = null, ?string $operation = null): self
    {
        return new self(
            tenantId: $tenantId ?? self::currentTenantId(),
            referenceType: 'article_drafts',
            referenceId: $draftId,
            operation: $operation,
        );
    }

    public function withOperation(string $operation): self
    {
        return new self($this->tenantId, $this->referenceType, $this->referenceId, $operation);
    }

    public function withTenantId(?int $tenantId): self
    {
        return new self($tenantId, $this->referenceType, $this->referenceId, $this->operation);
    }

    /**
     * Der Entwurf, um den es geht — null, wenn der Aufruf an keinem haengt.
     */
    public function draftId(): ?int
    {
        return $this->referenceType === 'article_drafts' ? $this->referenceId : null;
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
