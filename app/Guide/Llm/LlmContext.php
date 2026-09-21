<?php

declare(strict_types=1);

namespace App\Guide\Llm;

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

    public function withOperation(string $operation): self
    {
        return new self($this->tenantId, $this->referenceType, $this->referenceId, $operation);
    }

    public function withTenantId(?int $tenantId): self
    {
        return new self($tenantId, $this->referenceType, $this->referenceId, $this->operation);
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
