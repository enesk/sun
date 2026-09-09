<?php

declare(strict_types=1);

namespace App\Content\Sources\Contracts;

use App\Content\Sources\SourceItemDto;
use App\Content\Sources\TenantContext;
use Illuminate\Support\Collection;

/**
 * Eine Quelle der Themenfindung (#7-#11).
 *
 * Implementierungen werden im ContentServiceProvider als getaggte Services
 * registriert und vom SourceRunner ueber die SourceRegistry gefunden. Der
 * Runner kennt keinen Connector namentlich.
 */
interface SourceConnector
{
    /**
     * Stabiler Schluessel, z. B. 'google_trends'. Landet in
     * source_items.source_key und in provider_states.provider.
     */
    public function key(): string;

    /**
     * Rohsignale des Mandanten holen. Der Aufruf laeuft bereits im
     * Tenant-Kontext; Ausnahmen werden vom Runner abgefangen und als
     * Provider-Fehler protokolliert.
     *
     * @return Collection<int, SourceItemDto>
     */
    public function fetch(TenantContext $context): Collection;

    /**
     * Abrufrhythmus als Wert von App\Content\Enums\SourceFrequency:
     * 'hourly', 'six_hourly', 'daily' oder 'weekly'.
     */
    public function schedule(): string;
}
