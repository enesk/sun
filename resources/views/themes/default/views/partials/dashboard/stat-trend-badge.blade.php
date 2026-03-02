{{-- Trend-Badge für Chart-Header: Kompakte Anzeige der prozentualen Veränderung --}}
@if($change > 0)
    <span class="dash-trend-badge dash-trend-badge-up">+{{ number_format($change, 1) }}%</span>
@elseif($change < 0)
    <span class="dash-trend-badge dash-trend-badge-down">{{ number_format($change, 1) }}%</span>
@else
    <span class="dash-trend-badge dash-trend-badge-flat">0%</span>
@endif
