{{-- Job Card Partial — @param Job $job, @param bool $expired (optional) --}}
@php
    $isExpired = isset($expired) && $expired;
    $badgeColors = [
        'vollzeit' => 'var(--portal-primary)',
        'teilzeit' => 'rgb(20 184 166)',
        'minijob' => 'var(--portal-accent)',
        'ausbildung' => 'rgb(168 85 247)',
        'praktikum' => 'rgb(34 197 94)',
    ];
    $color = $badgeColors[$job->employment_type] ?? 'var(--portal-primary)';
@endphp

<article class="dash-card dash-card-padded dash-job-card {{ $isExpired ? 'dash-job-card--expired' : '' }}"
         aria-label="{{ $job->title }}{{ $isExpired ? ' — abgelaufen' : '' }}">
    <div class="flex flex-col sm:flex-row sm:items-start gap-4">
        {{-- Job Info --}}
        <div class="flex-1 min-w-0">
            <div class="flex items-center gap-2 flex-wrap">
                <h3 class="font-semibold text-base truncate" style="color: var(--dash-text-primary)">{{ $job->title }}</h3>

                <span class="dash-badge" style="background-color: {{ $color }}; color: white; font-size: 0.6875rem;">
                    {{ \App\Models\Portal\Job::EMPLOYMENT_TYPES[$job->employment_type] ?? $job->employment_type }}
                </span>

                @if($isExpired)
                    <span class="dash-badge dash-badge-warning">Abgelaufen</span>
                @elseif(!$job->is_active)
                    <span class="dash-badge dash-badge-neutral">Inaktiv</span>
                @endif
            </div>

            {{-- Meta --}}
            <div class="flex items-center gap-3 mt-1.5 text-xs flex-wrap" style="color: var(--dash-text-muted)">
                @if($job->location_display)
                    <span class="flex items-center gap-1">
                        <svg class="w-3.5 h-3.5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/>
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/>
                        </svg>
                        {{ $job->location_display }}
                    </span>
                @endif

                @if($job->salary_display)
                    <span class="flex items-center gap-1">
                        <svg class="w-3.5 h-3.5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                        {{ $job->salary_display }}
                    </span>
                @endif

                @if($job->is_live)
                    <span class="flex items-center gap-1">
                        <svg class="w-3.5 h-3.5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                        Noch {{ $job->days_remaining }} Tage
                    </span>
                @elseif($job->expires_at)
                    <span>Abgelaufen am {{ $job->expires_at->format('d.m.Y') }}</span>
                @endif
            </div>

            {{-- Stats --}}
            <div class="flex items-center gap-4 mt-2 text-xs" style="color: var(--dash-text-secondary)">
                <span class="flex items-center gap-1" aria-label="{{ number_format($job->views_count) }} Aufrufe">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                    </svg>
                    {{ number_format($job->views_count) }} Aufrufe
                </span>
                <a href="{{ route('portal.owner.jobs.applications', $job->id) }}"
                   class="flex items-center gap-1 hover:underline" style="color: var(--portal-primary)">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/>
                    </svg>
                    {{ $job->applications_count }} {{ $job->applications_count === 1 ? 'Bewerbung' : 'Bewerbungen' }}
                </a>
            </div>
        </div>

        {{-- Actions --}}
        <div class="flex items-center gap-2 shrink-0" x-data="{ confirmDelete: false }" role="group" aria-label="Aktionen für {{ $job->title }}">
            @if(!$isExpired)
                <a href="{{ route('portal.owner.jobs.edit', $job->id) }}"
                   class="dash-btn dash-btn-sm dash-btn-secondary"
                   title="Bearbeiten"
                   aria-label="{{ $job->title }} bearbeiten">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                    </svg>
                </a>
            @endif

            {{-- Toggle Active/Inactive --}}
            <form action="{{ route('portal.owner.jobs.toggle', $job->id) }}" method="POST">
                @csrf
                <button type="submit"
                        class="dash-btn dash-btn-sm {{ $job->is_active && !$isExpired ? '' : 'dash-btn-primary' }}"
                        @if($job->is_active && !$isExpired)
                            style="color: var(--dash-warning); border-color: rgba(217, 119, 6, 0.2);"
                        @endif
                        title="{{ $job->is_active && !$isExpired ? 'Deaktivieren' : 'Reaktivieren' }}"
                        aria-label="{{ $job->title }} {{ $job->is_active && !$isExpired ? 'deaktivieren' : 'reaktivieren' }}">
                    @if($job->is_active && !$isExpired)
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 9v6m4-6v6m7-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                    @else
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14.752 11.168l-3.197-2.132A1 1 0 0010 9.87v4.263a1 1 0 001.555.832l3.197-2.132a1 1 0 000-1.664z"/>
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                    @endif
                </button>
            </form>

            {{-- Delete --}}
            <button type="button"
                    @click="confirmDelete = true"
                    x-show="!confirmDelete"
                    class="dash-btn dash-btn-sm"
                    style="color: var(--dash-danger); border-color: rgba(220, 38, 38, 0.2);"
                    title="Löschen"
                    aria-label="{{ $job->title }} löschen">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                </svg>
            </button>

            {{-- Delete Confirmation --}}
            <div x-show="confirmDelete" x-cloak x-transition class="flex items-center gap-2" role="alertdialog" aria-label="Löschen bestätigen">
                <form action="{{ route('portal.owner.jobs.destroy', $job->id) }}" method="POST">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="dash-btn dash-btn-sm" style="background: var(--dash-danger); color: white;">
                        Löschen
                    </button>
                </form>
                <button type="button" @click="confirmDelete = false" class="dash-btn dash-btn-sm dash-btn-secondary">
                    Abbrechen
                </button>
            </div>
        </div>
    </div>
</article>
