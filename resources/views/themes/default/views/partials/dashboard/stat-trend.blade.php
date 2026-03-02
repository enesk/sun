{{-- Trend-Indikator für Stat-Cards: +12%, -5%, oder stabil --}}
@if($change !== null)
    @if($change > 0)
        <span class="dash-stat-trend dash-stat-trend-up">
            <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 10l7-7m0 0l7 7m-7-7v18"/>
            </svg>
            +{{ number_format($change, 1) }}%
        </span>
    @elseif($change < 0)
        <span class="dash-stat-trend dash-stat-trend-down">
            <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 14l-7 7m0 0l-7-7m7 7V3"/>
            </svg>
            {{ number_format($change, 1) }}%
        </span>
    @else
        <span class="dash-stat-trend dash-stat-trend-flat">stabil</span>
    @endif
@else
    <span class="dash-stat-sub">Noch keine Vergleichsdaten</span>
@endif
