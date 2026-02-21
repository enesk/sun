<div>
    {{-- Period selector --}}
    <div class="flex items-center justify-between mb-4">
        <h2 class="dash-card-header-title">Kennzahlen</h2>
        <select wire:model.live="period"
                class="dash-select dash-btn-sm"
                style="width: auto; min-height: auto; padding: 0.375rem 2rem 0.375rem 0.5rem;"
                aria-label="Zeitraum">
            <option value="7">Letzte 7 Tage</option>
            <option value="30">Letzte 30 Tage</option>
            <option value="90">Letzte 90 Tage</option>
            <option value="365">Letztes Jahr</option>
        </select>
    </div>

    {{-- KPI Grid --}}
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-3 sm:gap-4">
        @foreach($stats as $stat)
            <div class="dash-stat-card">
                <div class="flex items-center justify-between mb-1">
                    <span class="dash-stat-label">{{ $stat['label'] }}</span>
                    @if($stat['trend'])
                        <span class="dash-stat-trend {{ $stat['trend']['direction'] === 'up' ? 'dash-stat-trend-up' : ($stat['trend']['direction'] === 'down' ? 'dash-stat-trend-down' : 'dash-stat-trend-flat') }}">
                            @if($stat['trend']['direction'] === 'up')
                                <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M4.5 19.5l15-15m0 0H8.25m11.25 0v11.25"/></svg>
                            @elseif($stat['trend']['direction'] === 'down')
                                <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M4.5 4.5l15 15m0 0V8.25m0 11.25H8.25"/></svg>
                            @endif
                            {{ $stat['trend']['label'] }}
                        </span>
                    @endif
                </div>

                <span class="dash-stat-value">{{ $stat['value'] }}</span>

                <span class="dash-stat-sub {{ ($stat['highlight'] ?? false) ? 'font-medium' : '' }}"
                      @if($stat['highlight'] ?? false) style="color: var(--portal-accent, #f59e0b);" @endif>
                    {{ $stat['sub'] }}
                </span>
            </div>
        @endforeach
    </div>
</div>
