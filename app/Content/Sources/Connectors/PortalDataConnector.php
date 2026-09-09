<?php

declare(strict_types=1);

namespace App\Content\Sources\Connectors;

use App\Content\Enums\SourceFrequency;
use App\Content\Jobs\ClusterLeadQuestionsJob;
use App\Content\Services\PortalDataProvider;
use App\Content\Sources\Contracts\SourceConnector;
use App\Content\Sources\TenantContext;
use Illuminate\Support\Collection;

/**
 * Eigendaten des Portals im Quell-Framework (#11), woechentlich.
 *
 * Duenne Huelle um den PortalDataProvider: die Aggregation selbst gehoert in
 * einen Dienst, weil der Generator (#14) sie auch auf Zuruf braucht. Als
 * Connector laeuft sie zusaetzlich im normalen Rhythmus mit — damit gelten
 * Scheduler, Faelligkeitspruefung, Provider-Status und Quellen-Monitor auch
 * fuer die Eigendaten, ohne dass es dafuer einen eigenen Command braucht.
 *
 * Im selben Takt wird das Clustering der Lead-Freitexte angestossen. Es
 * laeuft als eigener Job, weil es Modellkosten verursacht und nicht am
 * Zeitfenster des Connector-Laufs haengen soll.
 */
class PortalDataConnector implements SourceConnector
{
    public function __construct(
        private readonly PortalDataProvider $provider,
    ) {}

    public function key(): string
    {
        return 'portal_data';
    }

    public function schedule(): string
    {
        return SourceFrequency::WEEKLY->value;
    }

    public function fetch(TenantContext $context): Collection
    {
        if ((bool) config('content.sources.lead_questions.enabled', false)) {
            ClusterLeadQuestionsJob::dispatch($context->tenantId);
        }

        return $this->provider->refresh($context);
    }
}
