<div>
    <div class="flex items-center justify-between mb-4">
        <h2 class="text-base font-semibold" style="color: var(--dash-text-primary);">Neueste Firmen</h2>
        <a href="{{ route('verwaltung.companies.index') }}" class="text-xs font-medium" style="color: var(--portal-primary, #3b82f6); text-decoration: none;"
           onmouseover="this.style.textDecoration='underline'" onmouseout="this.style.textDecoration='none'">
            Alle ansehen
        </a>
    </div>

    @if($companies->isEmpty())
        <div class="dash-empty" style="padding: 2rem 1rem;">
            <svg class="dash-empty-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5">
                <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 21h16.5M4.5 3h15M5.25 3v18m13.5-18v18M9 6.75h1.5m-1.5 3h1.5m-1.5 3h1.5m3-6H15m-1.5 3H15m-1.5 3H15M9 21v-3.375c0-.621.504-1.125 1.125-1.125h3.75c.621 0 1.125.504 1.125 1.125V21"/>
            </svg>
            <p class="dash-empty-title">Noch keine Firmen eingetragen</p>
        </div>
    @else
        <div class="space-y-3">
            @foreach($companies as $company)
                <div class="flex items-center gap-3 p-3 rounded-lg" style="background-color: var(--dash-surface); border: 1px solid var(--dash-border);">
                    <div class="w-10 h-10 rounded-lg flex items-center justify-center text-sm font-bold text-white shrink-0"
                         style="background-color: var(--portal-primary, #3b82f6);">
                        {{ strtoupper(substr($company->name, 0, 1)) }}
                    </div>
                    <div class="flex-1 min-w-0">
                        <span class="text-sm font-medium block truncate" style="color: var(--dash-text-primary);">{{ $company->name }}</span>
                        <div class="flex items-center gap-2 text-xs" style="color: var(--dash-text-muted);">
                            @if($company->city)
                                <span>{{ $company->city->name }}</span>
                            @endif
                            <span>{{ $company->created_at->diffForHumans() }}</span>
                        </div>
                    </div>
                    <div class="shrink-0">
                        @if($company->is_active)
                            <span class="dash-badge dash-badge-success">Aktiv</span>
                        @else
                            <span class="dash-badge dash-badge-neutral">Entwurf</span>
                        @endif
                    </div>
                </div>
            @endforeach
        </div>
    @endif
</div>
