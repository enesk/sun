<div>
    {{-- Period selector --}}
    <div class="flex items-center justify-between mb-4">
        <h2 class="text-base font-semibold text-base-content">Kennzahlen</h2>
        <select wire:model.live="period"
                class="text-xs border border-base-200 rounded-lg px-2 py-1.5 bg-base-100 text-base-content/70 focus:outline-none focus:ring-1"
                style="focus:ring-color: var(--portal-primary, #3b82f6);"
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
            <div class="card-portal flex flex-col">
                <div class="flex items-center justify-between mb-1">
                    <span class="text-xs font-medium uppercase tracking-wider text-base-content/50">
                        {{ $stat['label'] }}
                    </span>
                    @if($stat['trend'])
                        <span class="inline-flex items-center gap-0.5 text-xs font-medium
                            {{ $stat['trend']['direction'] === 'up' ? 'text-green-600' : ($stat['trend']['direction'] === 'down' ? 'text-red-500' : 'text-base-content/40') }}">
                            @if($stat['trend']['direction'] === 'up')
                                <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M4.5 19.5l15-15m0 0H8.25m11.25 0v11.25"/></svg>
                            @elseif($stat['trend']['direction'] === 'down')
                                <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M4.5 4.5l15 15m0 0V8.25m0 11.25H8.25"/></svg>
                            @endif
                            {{ $stat['trend']['label'] }}
                        </span>
                    @endif
                </div>

                <span class="text-2xl font-bold text-base-content">{{ $stat['value'] }}</span>

                <span class="text-xs mt-1 {{ ($stat['highlight'] ?? false) ? 'font-medium' : 'text-base-content/50' }}"
                      @if($stat['highlight'] ?? false) style="color: var(--portal-accent, #f59e0b);" @endif>
                    {{ $stat['sub'] }}
                </span>
            </div>
        @endforeach
    </div>
</div>
