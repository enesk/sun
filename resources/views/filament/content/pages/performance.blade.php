{{--
    Leistung (#25), Aufbau nach design/content-dashboard.md, §5.

    Vier Reiter (Artikel · Cluster · Regionen · Kosten), Standardzeitraum
    30 Tage. Alle Zahlen kommen aus article_metrics und llm_usage_logs; die
    Seite ruft keine Schnittstelle auf. An jeder Kennzahl steht der
    Datenstand — Search-Console-Zahlen hinken zwei bis drei Tage nach, und
    eine Zahl ohne Stand verleitet zu falschen Schluessen.

    Farben ausschliesslich ueber die Token aus resources/css/content/theme.css.
--}}
@php
    use App\Content\Services\PerformanceDashboardService as Performance;
    use App\Filament\Content\Pages\Performance as PerformancePage;

    $snapshot = $this->getSnapshot();
    $totals = $snapshot['totals'];
    $change = $snapshot['change'];

    $int = fn (?int $value): string => $value === null ? '—' : number_format($value, 0, ',', '.');
    $dec = fn (?float $value, int $digits = 1): string => $value === null
        ? '—'
        : number_format($value, $digits, ',', '.');

    $asOf = $snapshot['as_of']
        ? __('Datenstand :date', ['date' => \Illuminate\Support\Carbon::parse($snapshot['as_of'])->format('d.m.Y')])
        : __('Noch kein Datenstand');

    // Pfeil plus Vorzeichen, nicht nur Farbe: die Richtung muss auch ohne
    // Farbwahrnehmung ablesbar sein.
    $trend = function (?float $percent, bool $lowerIsBetter = false) {
        if ($percent === null) {
            return null;
        }

        $good = $lowerIsBetter ? $percent < 0 : $percent > 0;
        $arrow = $percent > 0 ? '↑' : ($percent < 0 ? '↓' : '→');

        return [
            'text' => $arrow.' '.($percent > 0 ? '+' : '').number_format($percent, 1, ',', '.').' %',
            'color' => $percent == 0.0
                ? 'var(--color-text-muted)'
                : ($good ? 'var(--color-score-good)' : 'var(--color-score-poor)'),
        ];
    };

    $kpis = [
        ['label' => __('Impressionen'), 'value' => $int($totals['impressions']), 'trend' => $trend($change['impressions'])],
        ['label' => __('Klicks'), 'value' => $int($totals['clicks']), 'trend' => $trend($change['clicks'])],
        ['label' => __('Mittlere Position'), 'value' => $dec($totals['position'], 1), 'trend' => $trend($change['position'], true)],
        [
            'label' => __('AdSense-Ertrag (USD)'),
            'value' => $totals['revenue_usd'] === null ? __('nicht gemessen') : $dec($totals['revenue_usd'], 2),
            'trend' => $trend($change['revenue_usd']),
        ],
    ];

    $columns = [
        ['key' => 'titel', 'label' => __('Titel'), 'class' => 'min-w-[280px]', 'align' => 'text-start'],
        ['key' => 'portal', 'label' => __('Portal'), 'class' => 'w-[150px]', 'align' => 'text-start'],
        ['key' => 'status', 'label' => __('Status'), 'class' => 'w-[140px]', 'align' => 'text-start'],
        ['key' => 'impressionen', 'label' => __('Impr.'), 'class' => 'w-[100px]', 'align' => 'text-end'],
        ['key' => 'klicks', 'label' => __('Klicks'), 'class' => 'w-[90px]', 'align' => 'text-end'],
        ['key' => 'position', 'label' => __('Position'), 'class' => 'w-[90px]', 'align' => 'text-end'],
        ['key' => 'ertrag', 'label' => __('Ertrag'), 'class' => 'w-[100px]', 'align' => 'text-end'],
    ];

    $list = $this->tab === PerformancePage::TAB_ARTICLES ? $this->getList() : null;

    $ariaSort = function (string $key) use ($list) {
        if ($list === null || $key !== $list['sort']) {
            return 'none';
        }

        return $list['direction'] === Performance::DIRECTION_ASC ? 'ascending' : 'descending';
    };

    $arrow = ($list['direction'] ?? Performance::DIRECTION_DESC) === Performance::DIRECTION_ASC ? '↑' : '↓';
@endphp

<x-filament-panels::page>
    <div class="space-y-content-8">
        {{-- A. Zeitraum, Portalfilter, Export --}}
        <div class="flex flex-wrap items-end gap-content-4">
            <div role="group" aria-label="{{ __('Zeitraum') }}" class="flex items-center gap-content-1">
                @foreach ($this->getPeriodOptions() as $value => $label)
                    <button
                        type="button"
                        wire:click="setPeriod({{ (int) $value }})"
                        aria-pressed="{{ $this->days === (int) $value ? 'true' : 'false' }}"
                        @class([
                            'h-9 rounded-content-md px-content-4 text-content-table font-medium',
                            'bg-content-600 text-white' => $this->days === (int) $value,
                            'bg-surface-sunken text-text-base' => $this->days !== (int) $value,
                        ])
                    >
                        {{ $label }}
                    </button>
                @endforeach
            </div>

            @if ($this->getPortalOptions() !== [])
                <label class="flex flex-col gap-content-1">
                    <span class="text-content-label text-text-base">{{ __('Portal') }}</span>
                    <select
                        wire:model.live="portal"
                        class="h-9 rounded-content-md border border-line-soft bg-surface-card px-content-3 text-content-table text-text-base"
                    >
                        <option value="">{{ __('Alle Portale') }}</option>
                        @foreach ($this->getPortalOptions() as $id => $name)
                            <option value="{{ $id }}">{{ $name }}</option>
                        @endforeach
                    </select>
                </label>
            @endif

            <div class="ms-auto flex items-center gap-content-3">
                <span class="content-asof">{{ $asOf }}</span>

                <button
                    type="button"
                    wire:click="exportCsv"
                    class="h-9 rounded-content-md border border-line-soft px-content-4 text-content-table font-medium text-text-base"
                >
                    {{ __('Als CSV exportieren') }}
                </button>
            </div>
        </div>

        {{-- Teilweise: Portale, die nicht gelesen werden konnten. --}}
        @if ($snapshot['unavailable'] !== [])
            <p
                class="rounded-content-lg border-s-[3px] p-content-3 text-content-body"
                style="background: var(--color-status-review-bg); border-color: var(--color-status-review-dot)"
                role="status"
            >
                {{ trans_choice(
                    '{1}Für ein Portal fehlen die Zahlen.|[2,*]Für :count Portale fehlen die Zahlen.',
                    count($snapshot['unavailable']),
                    ['count' => count($snapshot['unavailable'])],
                ) }}
                <span class="text-text-muted">{{ implode(', ', $snapshot['unavailable']) }}</span>
            </p>
        @endif

        {{-- B. Kennzahlkacheln --}}
        <div class="grid gap-content-4 sm:grid-cols-2 lg:grid-cols-4">
            @foreach ($kpis as $kpi)
                <div class="rounded-content-lg bg-surface-card p-content-6 shadow-content-card">
                    <p class="text-content-label text-text-base">{{ $kpi['label'] }}</p>
                    <p class="mt-content-1 text-content-h1 font-semibold leading-content-tight text-text-strong">
                        {{ $kpi['value'] }}
                    </p>

                    @if ($kpi['trend'])
                        <p class="mt-content-1 text-content-label" style="color: {{ $kpi['trend']['color'] }}">
                            {{ $kpi['trend']['text'] }}
                            <span class="text-text-muted">{{ __('gegenüber der Vorperiode') }}</span>
                        </p>
                    @else
                        <p class="mt-content-1 text-content-label text-text-muted">{{ __('kein Vergleichswert') }}</p>
                    @endif

                    <p class="content-asof mt-content-2">{{ $asOf }}</p>
                </div>
            @endforeach
        </div>

        {{-- C. Reiter --}}
        <div
            class="flex items-center gap-content-1 border-b border-line-soft"
            role="tablist"
            aria-label="{{ __('Ansicht') }}"
        >
            @foreach ($this->getTabs() as $value => $label)
                <button
                    type="button"
                    role="tab"
                    aria-selected="{{ $this->tab === $value ? 'true' : 'false' }}"
                    wire:click="switchTab('{{ $value }}')"
                    @class([
                        'h-11 px-content-4 text-content-table font-medium',
                        'border-b-2 border-content-600 text-content-700' => $this->tab === $value,
                        'text-text-muted' => $this->tab !== $value,
                    ])
                >
                    {{ $label }}
                </button>
            @endforeach
        </div>

        @if ($this->tab === PerformancePage::TAB_ARTICLES)
            @include('content.partials.performance-articles', [
                'snapshot' => $snapshot,
                'list' => $list,
                'columns' => $columns,
                'ariaSort' => $ariaSort,
                'arrow' => $arrow,
                'int' => $int,
                'dec' => $dec,
            ])
        @elseif ($this->tab === PerformancePage::TAB_CLUSTERS)
            @livewire(
                \App\Filament\Content\Widgets\ClicksByClusterChart::class,
                $this->getChartData(),
                key('cluster-chart-'.$this->days.'-'.($this->portal ?: 'alle'))
            )

            @include('content.partials.performance-groups', [
                'groups' => $snapshot['clusters'],
                'heading' => __('Cluster'),
                'empty' => __('Für diesen Zeitraum liegen keine Clusterzahlen vor.'),
                'int' => $int,
                'dec' => $dec,
            ])
        @elseif ($this->tab === PerformancePage::TAB_REGIONS)
            @livewire(
                \App\Filament\Content\Widgets\ClicksByRegionChart::class,
                $this->getChartData(),
                key('region-chart-'.$this->days.'-'.($this->portal ?: 'alle'))
            )

            @include('content.partials.performance-groups', [
                'groups' => $snapshot['regions'],
                'heading' => __('Region'),
                'empty' => __('Für diesen Zeitraum liegen keine Regionszahlen vor.'),
                'int' => $int,
                'dec' => $dec,
            ])
        @elseif ($this->tab === PerformancePage::TAB_COSTS && $this->canSeeCosts())
            @livewire(
                \App\Filament\Content\Widgets\CostVsRevenueChart::class,
                $this->getChartData(),
                key('cost-chart-'.$this->days.'-'.($this->portal ?: 'alle'))
            )

            @include('content.partials.performance-costs', [
                'costs' => $snapshot['costs'],
                'days' => $this->days,
                'dec' => $dec,
                'int' => $int,
            ])
        @endif
    </div>
</x-filament-panels::page>
