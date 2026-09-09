<?php

declare(strict_types=1);

namespace App\Content\Livewire;

use App\Content\Enums\DisplayStatus;
use App\Content\Livewire\Concerns\HasPipelineCardActions;
use App\Content\Livewire\Concerns\HasPipelineFilters;
use App\Content\Services\ContentPipelineService;
use App\Content\Support\BranchResolver;
use App\Models\Tenant;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Schemas\Concerns\InteractsWithSchemas;
use Filament\Schemas\Contracts\HasSchemas;
use Illuminate\Contracts\View\View;
use Livewire\Component;

/**
 * Pipeline-Board der Produktionsansicht (#19).
 *
 * Kanban ueber die sieben DisplayStatus, nicht ueber DraftStatus/TopicStatus
 * (design/content-dashboard.md, §2). Die Karten werden von der Pipeline
 * bewegt, nicht von Hand — Ziehen und Ablegen wuerde Uebergaenge suggerieren,
 * die DraftStatus::allowedTransitions() gar nicht erlaubt. Erlaubte
 * Handlungen stehen als benannte Schaltflaechen im Detailblatt; sie sind mit
 * der Artikelliste geteilt (HasPipelineCardActions).
 *
 * Die Filter stehen in der URL und ueberleben damit den Ansichtswechsel
 * zwischen Board, Kalender und Liste.
 */
class PipelineBoard extends Component implements HasActions, HasSchemas
{
    use HasPipelineCardActions;
    use HasPipelineFilters;
    use InteractsWithActions;
    use InteractsWithSchemas;

    /**
     * Zeitpunkt der letzten Aktualisierung — das Board ist live, die Kopfzeile
     * sagt trotzdem, wie frisch der Stand ist.
     */
    public string $refreshedAt = '';

    public function mount(): void
    {
        $this->refreshedAt = now()->format('H:i:s');
    }

    /**
     * Wird vom Polling aufgerufen; die Spalten baut render() ohnehin neu.
     */
    public function refreshBoard(): void
    {
        $this->refreshedAt = now()->format('H:i:s');
    }

    protected function afterCardAction(): void
    {
        $this->refreshBoard();
    }

    public function render(ContentPipelineService $service): View
    {
        return view('content.pipeline-board', [
            'columns' => $service->board($this->filters()),
            'generationBudget' => $this->generationBudget(),
            'portalOptions' => $service->tenants()->mapWithKeys(
                fn (Tenant $tenant): array => [(string) $tenant->getKey() => (string) $tenant->name],
            )->all(),
            'statusOptions' => DisplayStatus::options(),
            'regionOptions' => $service->regionOptions(),
            'branchOptions' => BranchResolver::all(),
        ]);
    }
}
