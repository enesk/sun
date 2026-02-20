<div>
    <div class="flex items-center justify-between mb-4">
        <h2 class="text-base font-semibold text-base-content">Neueste Firmen</h2>
        <a href="{{ route('verwaltung.companies.index') }}" class="text-xs font-medium hover:underline" style="color: var(--portal-primary, #3b82f6);">
            Alle ansehen
        </a>
    </div>

    @if($companies->isEmpty())
        <div class="text-center py-8">
            <svg class="w-10 h-10 mx-auto text-base-content/15 mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5">
                <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 21h16.5M4.5 3h15M5.25 3v18m13.5-18v18M9 6.75h1.5m-1.5 3h1.5m-1.5 3h1.5m3-6H15m-1.5 3H15m-1.5 3H15M9 21v-3.375c0-.621.504-1.125 1.125-1.125h3.75c.621 0 1.125.504 1.125 1.125V21"/>
            </svg>
            <p class="text-sm text-base-content/40">Noch keine Firmen eingetragen</p>
        </div>
    @else
        <div class="space-y-3">
            @foreach($companies as $company)
                <div class="flex items-center gap-3 p-3 rounded-lg bg-base-100 border border-base-200">
                    <div class="w-10 h-10 rounded-lg flex items-center justify-center text-sm font-bold text-white shrink-0"
                         style="background-color: var(--portal-primary, #3b82f6);">
                        {{ strtoupper(substr($company->name, 0, 1)) }}
                    </div>
                    <div class="flex-1 min-w-0">
                        <span class="text-sm font-medium text-base-content block truncate">{{ $company->name }}</span>
                        <div class="flex items-center gap-2 text-xs text-base-content/50">
                            @if($company->city)
                                <span>{{ $company->city->name }}</span>
                            @endif
                            <span>{{ $company->created_at->diffForHumans() }}</span>
                        </div>
                    </div>
                    <div class="shrink-0">
                        @if($company->is_active)
                            <span class="inline-flex items-center px-1.5 py-0.5 rounded text-xs font-medium bg-green-100 text-green-700">Aktiv</span>
                        @else
                            <span class="inline-flex items-center px-1.5 py-0.5 rounded text-xs font-medium bg-base-200 text-base-content/50">Entwurf</span>
                        @endif
                    </div>
                </div>
            @endforeach
        </div>
    @endif
</div>
