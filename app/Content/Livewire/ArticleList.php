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
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * Artikelliste der Produktionsansicht (#36), design/content-dashboard.md, §3a.
 *
 * Das Board ist die Uebersicht des Tages, der Kalender die Zeitachse, die
 * Liste der vollstaendige Bestand: sortierbar, durchblaetterbar, teilbar.
 * Filter, Sortierung und Seite stehen in der URL und ueberleben den Wechsel
 * zwischen den drei Reitern.
 *
 * Kein Polling: eine Liste, die unter der Hand die Zeilen tauscht, verliert
 * die Zeile, die man gerade lesen wollte. Stattdessen traegt der Filterbalken
 * den Stand und daneben die Schaltflaeche "Aktualisieren".
 */
class ArticleList extends Component implements HasActions, HasSchemas
{
    use HasPipelineCardActions;
    use HasPipelineFilters;
    use InteractsWithActions;
    use InteractsWithSchemas;

    #[Url(as: 'sortieren', history: true)]
    public string $sort = ContentPipelineService::SORT_DEFAULT;

    #[Url(as: 'richtung', history: true)]
    public string $direction = ContentPipelineService::DIRECTION_ASC;

    #[Url(as: 'seite', history: true)]
    public int $page = 1;

    /**
     * Stand der Liste als "Stand: 09:14" im Filterbalken.
     */
    public string $refreshedAt = '';

    public function mount(): void
    {
        $this->refreshedAt = now()->format('H:i');
    }

    public function refreshList(): void
    {
        $this->refreshedAt = now()->format('H:i');
    }

    /**
     * Jede Filter- oder Sortieraenderung setzt auf Seite 1 zurueck. Sonst
     * zeigt Seite 7 nach einem engeren Filter eine leere Tabelle.
     */
    public function updated(string $property, mixed $value = null): void
    {
        if (! str_starts_with($property, 'page')) {
            $this->page = 1;
        }
    }

    public function resetFilters(): void
    {
        $this->portals = [];
        $this->statuses = [];
        $this->regions = [];
        $this->branches = [];
        $this->page = 1;
    }

    /**
     * Klick auf eine Kopfzelle: gleiche Spalte wechselt die Richtung, eine
     * andere startet mit ihrer sinnvollen Erstrichtung.
     */
    public function sortBy(string $sort): void
    {
        if (! in_array($sort, ContentPipelineService::SORTS, true)) {
            return;
        }

        $this->direction = $sort === $this->sort
            ? ($this->direction === ContentPipelineService::DIRECTION_ASC
                ? ContentPipelineService::DIRECTION_DESC
                : ContentPipelineService::DIRECTION_ASC)
            : ContentPipelineService::initialDirection($sort);

        $this->sort = $sort;
        $this->page = 1;
    }

    /**
     * Klappliste der Mobilansicht: "termin:auf" in einem Zug.
     */
    public function applySort(string $value): void
    {
        [$sort, $direction] = array_pad(explode(':', $value, 2), 2, '');

        if (! in_array($sort, ContentPipelineService::SORTS, true)) {
            return;
        }

        $this->sort = $sort;
        $this->direction = $direction === ContentPipelineService::DIRECTION_DESC
            ? ContentPipelineService::DIRECTION_DESC
            : ContentPipelineService::DIRECTION_ASC;
        $this->page = 1;
    }

    public function goToPage(int $page): void
    {
        $this->page = max(1, $page);
    }

    protected function afterCardAction(): void
    {
        $this->refreshList();
    }

    /**
     * Sortierbare Spalten in fester Reihenfolge; Region fehlt bewusst.
     *
     * @return array<string, string>
     */
    public function sortOptions(): array
    {
        return [
            'termin' => __('Termin'),
            'score' => __('Score'),
            'titel' => __('Titel'),
            'portal' => __('Portal'),
            'status' => __('Status'),
        ];
    }

    public function render(ContentPipelineService $service): View
    {
        $result = $service->list($this->filters(), $this->sort, $this->direction, $this->page);

        // Die geklammerte Seite gilt: eine URL mit ?seite=99 landet auf der
        // letzten Seite statt auf einer leeren Tabelle.
        $this->page = $result['page'];
        $this->sort = $result['sort'];
        $this->direction = $result['direction'];

        return view('content.article-list', [
            'result' => $result,
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
