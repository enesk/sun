<div>
    {{-- Status Tabs --}}
    <div class="dash-tab-bar mb-4">
        @php
            $tabs = [
                '' => ['label' => 'Alle', 'count' => $statusCounts['all']],
                'active' => ['label' => 'Aktiv', 'count' => $statusCounts['active']],
                'expired' => ['label' => 'Abgelaufen / Inaktiv', 'count' => $statusCounts['expired']],
            ];
        @endphp

        @foreach($tabs as $value => $tab)
            <button wire:click="$set('filterStatus', '{{ $value }}')"
                    class="dash-tab {{ $filterStatus === $value ? 'dash-tab-active' : '' }}">
                {{ $tab['label'] }}
                @if($tab['count'] > 0)
                    <span class="dash-tab-count">
                        {{ $tab['count'] }}
                    </span>
                @endif
            </button>
        @endforeach
    </div>

    {{-- Filter Bar --}}
    <div class="dash-card mb-4">
        <div class="dash-filter-bar">
            {{-- Search --}}
            <div class="dash-filter-search">
                <svg class="dash-filter-search-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-5.197-5.197m0 0A7.5 7.5 0 105.196 5.196a7.5 7.5 0 0010.607 10.607z"/>
                </svg>
                <input type="text"
                       wire:model.live.debounce.300ms="search"
                       placeholder="Titel, Beschreibung oder Firmenname suchen..."
                       class="dash-input"
                       style="padding-left: 2.5rem;"
                       aria-label="Stellenanzeigen durchsuchen">
            </div>

            {{-- Filters --}}
            <div class="dash-filter-actions">
                <select wire:model.live="filterType"
                        class="dash-select dash-btn-sm"
                        style="width: auto; min-height: auto; padding: 0.5rem 2rem 0.5rem 0.75rem;"
                        aria-label="Beschäftigungsart filtern">
                    <option value="">Alle Arten</option>
                    <option value="vollzeit">Vollzeit</option>
                    <option value="teilzeit">Teilzeit</option>
                    <option value="minijob">Minijob</option>
                    <option value="ausbildung">Ausbildung</option>
                    <option value="praktikum">Praktikum</option>
                </select>

                <select wire:model.live="filterCompany"
                        class="dash-select dash-btn-sm"
                        style="width: auto; min-height: auto; padding: 0.5rem 2rem 0.5rem 0.75rem;"
                        aria-label="Firma filtern">
                    <option value="">Alle Firmen</option>
                    @foreach($companies as $id => $name)
                        <option value="{{ $id }}">{{ $name }}</option>
                    @endforeach
                </select>

                @if($search || $filterStatus || $filterType || $filterCompany)
                    <button wire:click="resetFilters"
                            class="dash-btn-icon"
                            title="Filter zurücksetzen">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/>
                        </svg>
                    </button>
                @endif
            </div>
        </div>

        {{-- Bulk Actions --}}
        @if(count($selected) > 0)
            <div class="dash-table-bulk">
                <span class="dash-table-bulk-count">{{ count($selected) }} ausgewählt</span>
                <button wire:click="bulkDeactivate"
                        wire:confirm="Ausgewählte Stellenanzeigen deaktivieren?"
                        class="dash-btn dash-btn-sm dash-btn-secondary"
                        style="min-height: auto; background-color: var(--dash-warning-light, #fef3c7); color: var(--dash-warning, #d97706); border-color: var(--dash-warning-border, #fcd34d);">
                    Deaktivieren
                </button>
                <button wire:click="bulkDelete"
                        wire:confirm="Ausgewählte Stellenanzeigen unwiderruflich löschen?"
                        class="dash-btn dash-btn-sm dash-btn-secondary"
                        style="min-height: auto; background-color: var(--dash-danger-light); color: var(--dash-danger); border-color: var(--dash-danger-border);">
                    Löschen
                </button>
            </div>
        @endif
    </div>

    {{-- Job Cards --}}
    <div class="space-y-3">
        @forelse($jobs as $job)
            <div class="dash-card dash-card-padded" wire:key="job-{{ $job->id }}">
                <div class="flex items-start gap-3">
                    {{-- Checkbox --}}
                    <input type="checkbox"
                           wire:model.live="selected"
                           value="{{ $job->id }}"
                           class="mt-1 shrink-0"
                           style="accent-color: var(--portal-primary, #3b82f6);"
                           aria-label="Stellenanzeige auswählen">

                    {{-- Content --}}
                    <div class="flex-1 min-w-0">
                        {{-- Header: Title + Status --}}
                        <div class="flex flex-wrap items-center gap-2 mb-1.5">
                            <h3 class="text-sm font-semibold" style="color: var(--dash-text-primary);">
                                {{ $job->title }}
                            </h3>

                            {{-- Status Badge --}}
                            @if($job->is_active && $job->expires_at && $job->expires_at->isFuture())
                                <span class="dash-badge dash-badge-success">Aktiv</span>
                            @elseif($job->is_active && $job->expires_at && $job->expires_at->isPast())
                                <span class="dash-badge dash-badge-warning">Abgelaufen</span>
                            @else
                                <span class="dash-badge dash-badge-danger">Inaktiv</span>
                            @endif

                            {{-- Employment Type Badge --}}
                            @php
                                $typeLabels = [
                                    'vollzeit' => 'Vollzeit',
                                    'teilzeit' => 'Teilzeit',
                                    'minijob' => 'Minijob',
                                    'ausbildung' => 'Ausbildung',
                                    'praktikum' => 'Praktikum',
                                ];
                            @endphp
                            <span class="dash-badge">{{ $typeLabels[$job->employment_type] ?? $job->employment_type }}</span>
                        </div>

                        {{-- Description excerpt --}}
                        @if($job->description)
                            <p class="text-sm mb-2 line-clamp-2" style="color: var(--dash-text-secondary);">
                                {{ Str::limit(strip_tags($job->description), 150) }}
                            </p>
                        @endif

                        {{-- Meta: Company + Location + Dates + Applications --}}
                        <div class="flex flex-wrap items-center gap-x-3 gap-y-1 text-xs" style="color: var(--dash-text-muted);">
                            {{-- Company --}}
                            @if($job->company)
                                <span class="inline-flex items-center gap-1">
                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 21h16.5M4.5 3h15M5.25 3v18m13.5-18v18M9 6.75h1.5m-1.5 3h1.5m-1.5 3h1.5m3-6H15m-1.5 3H15m-1.5 3H15M9 21v-3.375c0-.621.504-1.125 1.125-1.125h3.75c.621 0 1.125.504 1.125 1.125V21"/>
                                    </svg>
                                    {{ $job->company->name }}
                                    @if($job->company->is_premium)
                                        <span class="dash-badge dash-badge-info" style="font-size: 0.625rem; padding: 0 0.375rem;">Premium</span>
                                    @endif
                                </span>
                            @endif

                            {{-- Location --}}
                            @if($job->location || ($job->city && $job->city->name))
                                <span class="inline-flex items-center gap-1">
                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M15 10.5a3 3 0 11-6 0 3 3 0 016 0z"/>
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 10.5c0 7.142-7.5 11.25-7.5 11.25S4.5 17.642 4.5 10.5a7.5 7.5 0 1115 0z"/>
                                    </svg>
                                    {{ $job->location ?: $job->city->name }}
                                </span>
                            @endif

                            {{-- Created --}}
                            <span class="inline-flex items-center gap-1">
                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 012.25-2.25h13.5A2.25 2.25 0 0121 7.5v11.25m-18 0A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75m-18 0v-7.5A2.25 2.25 0 015.25 9h13.5A2.25 2.25 0 0121 11.25v7.5"/>
                                </svg>
                                {{ $job->created_at->format('d.m.Y') }}
                            </span>

                            {{-- Expires --}}
                            @if($job->expires_at)
                                <span class="inline-flex items-center gap-1"
                                      style="{{ $job->expires_at->isPast() ? 'color: var(--dash-danger);' : '' }}">
                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                    </svg>
                                    Läuft ab: {{ $job->expires_at->format('d.m.Y') }}
                                </span>
                            @endif

                            {{-- Applications count --}}
                            @if($job->applications_count > 0)
                                <span class="inline-flex items-center gap-1" style="color: var(--portal-primary, #3b82f6);">
                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M21.75 6.75v10.5a2.25 2.25 0 01-2.25 2.25h-15a2.25 2.25 0 01-2.25-2.25V6.75m19.5 0A2.25 2.25 0 0019.5 4.5h-15a2.25 2.25 0 00-2.25 2.25m19.5 0v.243a2.25 2.25 0 01-1.07 1.916l-7.5 4.615a2.25 2.25 0 01-2.36 0L3.32 8.91a2.25 2.25 0 01-1.07-1.916V6.75"/>
                                    </svg>
                                    {{ $job->applications_count }} {{ $job->applications_count === 1 ? 'Bewerbung' : 'Bewerbungen' }}
                                </span>
                            @endif

                            {{-- Salary --}}
                            @if($job->salary_min || $job->salary_max)
                                <span class="inline-flex items-center gap-1">
                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M14.25 7.756a4.5 4.5 0 100 8.488M7.5 10.5h5.25m-5.25 3h5.25M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                    </svg>
                                    @if($job->salary_min && $job->salary_max)
                                        {{ number_format($job->salary_min, 0, ',', '.') }}–{{ number_format($job->salary_max, 0, ',', '.') }} €
                                    @elseif($job->salary_min)
                                        ab {{ number_format($job->salary_min, 0, ',', '.') }} €
                                    @else
                                        bis {{ number_format($job->salary_max, 0, ',', '.') }} €
                                    @endif
                                </span>
                            @endif
                        </div>
                    </div>

                    {{-- Quick Actions --}}
                    <div class="flex items-center gap-1 shrink-0">
                        @if($job->is_active)
                            <button wire:click="deactivateJob({{ $job->id }})"
                                    wire:confirm="Stellenanzeige &quot;{{ $job->title }}&quot; deaktivieren?"
                                    class="dash-btn-icon"
                                    style="color: var(--dash-warning, #d97706);"
                                    title="Deaktivieren">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 5.25v13.5m-7.5-13.5v13.5"/>
                                </svg>
                            </button>
                        @else
                            <button wire:click="activateJob({{ $job->id }})"
                                    wire:confirm="Stellenanzeige &quot;{{ $job->title }}&quot; aktivieren (30 Tage)?"
                                    class="dash-btn-icon"
                                    style="color: var(--dash-success);"
                                    title="Aktivieren">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M5.25 5.653c0-.856.917-1.398 1.667-.986l11.54 6.348a1.125 1.125 0 010 1.971l-11.54 6.347a1.125 1.125 0 01-1.667-.985V5.653z"/>
                                </svg>
                            </button>
                        @endif

                        <button wire:click="deleteJob({{ $job->id }})"
                                wire:confirm="Stellenanzeige &quot;{{ $job->title }}&quot; unwiderruflich löschen?"
                                class="dash-btn-icon dash-btn-danger"
                                title="Löschen">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M14.74 9l-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 01-2.244 2.077H8.084a2.25 2.25 0 01-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 00-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 013.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 00-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 00-7.5 0"/>
                            </svg>
                        </button>
                    </div>
                </div>
            </div>
        @empty
            <div class="dash-card">
                <div class="dash-empty">
                    <svg class="dash-empty-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M20.25 14.15v4.25c0 1.094-.787 2.036-1.872 2.18-2.087.277-4.216.42-6.378.42s-4.291-.143-6.378-.42c-1.085-.144-1.872-1.086-1.872-2.18v-4.25m16.5 0a2.18 2.18 0 00.75-1.661V8.706c0-1.081-.768-2.015-1.837-2.175a48.114 48.114 0 00-3.413-.387m4.5 8.006c-.194.165-.42.295-.673.38A23.978 23.978 0 0112 15.75c-2.648 0-5.195-.429-7.577-1.22a2.016 2.016 0 01-.673-.38m0 0A2.18 2.18 0 013 12.489V8.706c0-1.081.768-2.015 1.837-2.175a48.111 48.111 0 013.413-.387m7.5 0V5.25A2.25 2.25 0 0013.5 3h-3a2.25 2.25 0 00-2.25 2.25v.894m7.5 0a48.667 48.667 0 00-7.5 0"/>
                    </svg>
                    @if($search || $filterStatus || $filterType || $filterCompany)
                        <p class="dash-empty-title">Keine Stellenanzeigen gefunden</p>
                        <p class="dash-empty-description">Versuchen Sie es mit anderen Suchbegriffen oder Filtern.</p>
                        <button wire:click="resetFilters" class="dash-btn dash-btn-sm dash-btn-primary">
                            Filter zurücksetzen
                        </button>
                    @else
                        <p class="dash-empty-title">Noch keine Stellenanzeigen vorhanden</p>
                        <p class="dash-empty-description">Stellenanzeigen erscheinen hier, sobald Firmeninhaber welche erstellen.</p>
                    @endif
                </div>
            </div>
        @endforelse
    </div>

    {{-- Pagination --}}
    @if($jobs->hasPages())
        <div class="dash-pagination mt-4">
            {{ $jobs->links() }}
        </div>
    @endif

    {{-- Result count --}}
    <div class="dash-result-count mt-2" style="border-top: none;">
        {{ $jobs->total() }} {{ $jobs->total() === 1 ? 'Stellenanzeige' : 'Stellenanzeigen' }} gefunden
    </div>
</div>
