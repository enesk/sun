<?php

declare(strict_types=1);

namespace App\Filament\Content\Pages;

use App\Content\Services\PerformanceDashboardService;
use App\Guide\Services\ContentTenantContext;
use App\Models\Tenant;
use App\Models\User;
use BackedEnum;
use Filament\Facades\Filament;
use Livewire\Attributes\Url;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Leistung: Artikel, Regionen und Kosten (#25),
 * design/content-dashboard.md, §5.
 *
 * Alle Zahlen kommen aus `article_metrics` (Tenant) und `llm_usage_logs`
 * (Central) ueber den PerformanceDashboardService. Aus dem Panel heraus wird
 * keine Schnittstelle abgefragt — Search Console und AdSense holt der
 * Metrik-Collector (#23) im Tageslauf.
 *
 * Der Reiter "Kosten" bleibt der Rolle `owner` vorbehalten; die uebrigen
 * Reiter stehen auch der Redaktion offen. Die Sperre wirkt nicht nur auf die
 * Anzeige des Reiters, sondern auch auf den Reiterwechsel und den Export.
 */
class Performance extends ContentPage
{
    public const TAB_ARTICLES = 'artikel';

    public const TAB_REGIONS = 'regionen';

    public const TAB_COSTS = 'kosten';

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-chart-bar';

    protected static ?int $navigationSort = 4;

    protected static ?string $slug = 'leistung';

    // Alte Pipeline: nicht in der Navigation, nur fuer Inhaber (#14).
    protected static bool $isLegacyPipelinePage = true;

    protected static string $followUpTicket = '#25';

    protected string $view = 'filament.content.pages.performance';

    #[Url(as: 'reiter', history: true)]
    public string $tab = self::TAB_ARTICLES;

    #[Url(as: 'zeitraum', history: true)]
    public int $days = PerformanceDashboardService::PERIOD_DEFAULT;

    /**
     * Portalfilter der Seite, leer heisst "alle". Bewusst als Zeichenkette:
     * eine leere Auswahl in einem `?int` liesse Livewire die Eigenschaft
     * uninitialisiert zuruecklassen.
     *
     * Der Filter wirkt zusaetzlich zur Auswahl im Kopfumschalter: steht die
     * auf einem Portal, gibt es hier nichts zu waehlen.
     */
    #[Url(as: 'portal', history: true)]
    public string $portal = '';

    #[Url(as: 'sortieren', history: true)]
    public string $sort = PerformanceDashboardService::SORT_DEFAULT;

    #[Url(as: 'richtung', history: true)]
    public string $direction = PerformanceDashboardService::DIRECTION_DESC;

    #[Url(as: 'seite', history: true)]
    public int $page = 1;

    public static function getNavigationLabel(): string
    {
        return __('Leistung');
    }

    public function getTitle(): string
    {
        return __('Leistung');
    }

    public function mount(): void
    {
        $this->days = $this->service()->period($this->days);

        if (! array_key_exists($this->tab, $this->getTabs())) {
            $this->tab = self::TAB_ARTICLES;
        }
    }

    public function canSeeCosts(): bool
    {
        $user = Filament::auth()->user();

        return $user instanceof User && $user->canSeeContentCosts();
    }

    /**
     * Reiter der Seite. Der Kostenreiter fehlt fuer die Redaktion ganz —
     * ein sichtbarer, aber gesperrter Reiter waere nur eine Einladung.
     *
     * @return array<string, string>
     */
    public function getTabs(): array
    {
        $tabs = [
            self::TAB_ARTICLES => __('Artikel'),
            self::TAB_REGIONS => __('Regionen'),
        ];

        if ($this->canSeeCosts()) {
            $tabs[self::TAB_COSTS] = __('Kosten');
        }

        return $tabs;
    }

    public function switchTab(string $tab): void
    {
        $this->tab = array_key_exists($tab, $this->getTabs()) ? $tab : self::TAB_ARTICLES;
    }

    public function setPeriod(int $days): void
    {
        $this->days = $this->service()->period($days);
        $this->page = 1;
    }

    /**
     * @return array<int, string>
     */
    public function getPeriodOptions(): array
    {
        $options = [];

        foreach (PerformanceDashboardService::PERIODS as $days) {
            $options[$days] = trans_choice('{1}Ein Tag|[2,*]:count Tage', $days, ['count' => $days]);
        }

        return $options;
    }

    /**
     * Portale des Filters. Steht der Kopfumschalter auf einem Portal, hat
     * der Filter keine Auswahl mehr.
     *
     * @return array<int, string>
     */
    public function getPortalOptions(): array
    {
        $context = app(ContentTenantContext::class);

        if ($context->selectedId() !== null) {
            return [];
        }

        $options = [];

        foreach ($context->available() as $tenant) {
            /** @var Tenant $tenant */
            $options[(int) $tenant->getKey()] = (string) $tenant->name;
        }

        asort($options);

        return $options;
    }

    public function updatedPortal(): void
    {
        $this->page = 1;
    }

    /**
     * Klick auf eine Kopfzelle: gleiche Spalte wechselt die Richtung, eine
     * andere startet mit ihrer sinnvollen Erstrichtung.
     */
    public function sortBy(string $sort): void
    {
        if (! in_array($sort, PerformanceDashboardService::SORTS, true)) {
            return;
        }

        $this->direction = $sort === $this->sort
            ? ($this->direction === PerformanceDashboardService::DIRECTION_ASC
                ? PerformanceDashboardService::DIRECTION_DESC
                : PerformanceDashboardService::DIRECTION_ASC)
            : PerformanceDashboardService::initialDirection($sort);

        $this->sort = $sort;
        $this->page = 1;
    }

    public function goToPage(int $page): void
    {
        $this->page = max(1, $page);
    }

    /**
     * @return array<string, mixed>
     */
    public function getSnapshot(): array
    {
        return $this->service()->snapshot($this->days, $this->filters());
    }

    /**
     * @return array<string, mixed>
     */
    public function getList(): array
    {
        return $this->service()->list($this->days, $this->filters(), $this->sort, $this->direction, $this->page);
    }

    /**
     * Werte, die die Diagramme als Livewire-Eigenschaften bekommen.
     *
     * @return array<string, mixed>
     */
    public function getChartData(): array
    {
        return [
            'days' => $this->days,
            'portals' => $this->filters()['portals'],
        ];
    }

    /**
     * Export der Tabelle im gerade eingestellten Zeitraum, Filter und
     * Sortierung — nicht nur der sichtbaren Seite.
     */
    public function exportCsv(): StreamedResponse
    {
        $csv = $this->service()->csv($this->days, $this->filters(), $this->sort, $this->direction);
        $name = 'leistung-'.$this->days.'-tage-'.now()->format('Y-m-d').'.csv';

        return response()->streamDownload(static function () use ($csv): void {
            echo $csv;
        }, $name, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /**
     * @return array<string, mixed>
     */
    private function filters(): array
    {
        return ['portals' => $this->portal === '' ? [] : [(int) $this->portal]];
    }

    private function service(): PerformanceDashboardService
    {
        return app(PerformanceDashboardService::class);
    }
}
