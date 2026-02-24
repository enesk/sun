<div>
@if($trial)
<div class="mx-4 sm:mx-6 lg:mx-8 mb-4">
    <div class="rounded-xl border p-4 sm:p-5 transition-colors duration-200
        @if($trial['urgency'] === 'critical')
            border-red-200 bg-red-50
        @elseif($trial['urgency'] === 'warning')
            border-amber-200 bg-amber-50
        @else
            border-blue-200 bg-blue-50
        @endif
    ">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
            {{-- Left: Trial info --}}
            <div class="flex items-start gap-3 min-w-0 flex-1">
                {{-- Icon --}}
                <div class="flex-shrink-0 mt-0.5">
                    @if($trial['urgency'] === 'critical')
                        <svg class="w-5 h-5 text-red-500" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.007v.008H12v-.008z"/>
                        </svg>
                    @else
                        <svg class="w-5 h-5 {{ $trial['urgency'] === 'warning' ? 'text-amber-500' : 'text-blue-500' }}" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6h4.5m4.5 0a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                    @endif
                </div>

                <div class="min-w-0">
                    <p class="text-sm font-semibold
                        @if($trial['urgency'] === 'critical') text-red-800
                        @elseif($trial['urgency'] === 'warning') text-amber-800
                        @else text-blue-800
                        @endif
                    ">
                        @if($trial['daysRemaining'] === 0)
                            Ihre {{ $trial['planName'] }}-Testphase endet heute!
                        @elseif($trial['daysRemaining'] === 1)
                            Noch 1 Tag in Ihrer {{ $trial['planName'] }}-Testphase
                        @else
                            Noch {{ $trial['daysRemaining'] }} Tage in Ihrer {{ $trial['planName'] }}-Testphase
                        @endif
                    </p>
                    <p class="text-xs mt-0.5
                        @if($trial['urgency'] === 'critical') text-red-600
                        @elseif($trial['urgency'] === 'warning') text-amber-600
                        @else text-blue-600
                        @endif
                    ">
                        Läuft ab am {{ $trial['endsAt'] }} · Alle Premium-Features aktiv
                    </p>

                    {{-- Progress bar --}}
                    <div class="mt-2 w-full max-w-xs">
                        <div class="h-1.5 rounded-full overflow-hidden
                            @if($trial['urgency'] === 'critical') bg-red-200
                            @elseif($trial['urgency'] === 'warning') bg-amber-200
                            @else bg-blue-200
                            @endif
                        ">
                            <div class="h-full rounded-full transition-all duration-500
                                @if($trial['urgency'] === 'critical') bg-red-500
                                @elseif($trial['urgency'] === 'warning') bg-amber-500
                                @else bg-blue-500
                                @endif
                            " style="width: {{ $trial['progress'] }}%"></div>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Right: CTA --}}
            <div class="flex-shrink-0 sm:ml-4">
                <a href="{{ $trial['convertUrl'] }}"
                   class="inline-flex items-center gap-1.5 px-4 py-2 text-sm font-medium rounded-lg transition-colors
                       @if($trial['urgency'] === 'critical')
                           bg-red-600 text-white hover:bg-red-700
                       @elseif($trial['urgency'] === 'warning')
                           bg-amber-600 text-white hover:bg-amber-700
                       @else
                           text-white hover:opacity-90
                       @endif
                   "
                   @if($trial['urgency'] === 'info')
                       style="background-color: var(--portal-primary, #3b82f6);"
                   @endif
                >
                    Jetzt Premium sichern
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.5 4.5L21 12m0 0l-7.5 7.5M21 12H3"/>
                    </svg>
                </a>
            </div>
        </div>
    </div>
</div>
@endif
</div>
