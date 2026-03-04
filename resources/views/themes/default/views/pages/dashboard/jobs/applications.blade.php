@extends('layouts.dashboard')

@section('title', 'Bewerbungen — ' . $job->title)

@section('content')
    <div class="dash-page-header">
        <div>
            <h1 class="dash-page-title">Bewerbungen</h1>
            <p class="dash-page-subtitle">{{ $job->title }} · {{ $applications->total() }} {{ $applications->total() === 1 ? 'Bewerbung' : 'Bewerbungen' }}</p>
        </div>
        <a href="{{ route('portal.owner.jobs.index') }}" class="dash-btn dash-btn-secondary dash-btn-sm">
            <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
            </svg>
            Zurück
        </a>
    </div>

    {{-- Job Status Info --}}
    <div class="dash-card dash-card-padded mb-6">
        <div class="flex items-center gap-3 text-sm flex-wrap" style="color: var(--dash-text-secondary)">
            <span class="font-medium" style="color: var(--dash-text-primary)">{{ $job->title }}</span>
            <span aria-hidden="true">·</span>
            <span>{{ \App\Models\Portal\Job::EMPLOYMENT_TYPES[$job->employment_type] ?? $job->employment_type }}</span>
            @if($job->is_live)
                <span class="dash-badge dash-badge-success">Aktiv</span>
            @else
                <span class="dash-badge dash-badge-warning">Inaktiv</span>
            @endif
        </div>
    </div>

    @if($applications->isEmpty())
        <div class="dash-card">
            <div class="dash-empty" style="padding: 3rem 1.5rem;">
                <svg class="dash-empty-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/>
                </svg>
                <h3 class="dash-empty-title">Noch keine Bewerbungen</h3>
                <p class="dash-empty-description">
                    Sobald Bewerber sich auf Ihre Stelle melden, erscheinen die Bewerbungen hier.
                </p>
            </div>
        </div>
    @else
        <div class="space-y-4" role="list" aria-label="Bewerbungen">
            @foreach($applications as $application)
                <article class="dash-card dash-card-padded" x-data="{ expanded: false }" role="listitem"
                         aria-label="Bewerbung von {{ $application->applicant_name }}">
                    <div class="flex flex-col sm:flex-row sm:items-start gap-3">
                        {{-- Applicant Info --}}
                        <div class="flex-1 min-w-0">
                            <div class="flex items-center gap-2 flex-wrap">
                                <h3 class="font-semibold text-sm" style="color: var(--dash-text-primary)">
                                    {{ $application->applicant_name }}
                                </h3>

                                @php
                                    $statusBadge = match($application->status) {
                                        'pending' => 'dash-badge-warning',
                                        'reviewed' => 'dash-badge-info',
                                        'contacted' => 'dash-badge-success',
                                        'rejected' => 'dash-badge-danger',
                                        default => 'dash-badge-neutral',
                                    };
                                @endphp
                                <span class="dash-badge {{ $statusBadge }}">{{ $application->status_label }}</span>
                            </div>

                            <div class="flex items-center gap-3 mt-1 text-xs flex-wrap" style="color: var(--dash-text-muted)">
                                <a href="mailto:{{ $application->applicant_email }}"
                                   class="flex items-center gap-1 hover:underline" style="color: var(--portal-primary)"
                                   aria-label="E-Mail an {{ $application->applicant_name }}">
                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/>
                                    </svg>
                                    {{ $application->applicant_email }}
                                </a>

                                @if($application->applicant_phone)
                                    <a href="tel:{{ $application->applicant_phone }}"
                                       class="flex items-center gap-1 hover:underline" style="color: var(--portal-primary)"
                                       aria-label="{{ $application->applicant_name }} anrufen">
                                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"/>
                                        </svg>
                                        {{ $application->applicant_phone }}
                                    </a>
                                @endif

                                <time datetime="{{ $application->created_at->toIso8601String() }}">
                                    {{ $application->created_at->format('d.m.Y H:i') }}
                                </time>
                            </div>

                            {{-- Message (expandable) --}}
                            @if($application->message)
                                <button type="button" @click="expanded = !expanded"
                                        class="text-xs font-medium mt-2 flex items-center gap-1 transition-colors hover:opacity-80"
                                        style="color: var(--portal-primary)"
                                        :aria-expanded="expanded.toString()">
                                    <svg class="w-3 h-3 transition-transform" :class="expanded && 'rotate-90'" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                                    </svg>
                                    <span x-text="expanded ? 'Nachricht verbergen' : 'Nachricht anzeigen'"></span>
                                </button>

                                <div x-show="expanded" x-cloak x-transition class="mt-2 p-3 rounded-lg text-sm"
                                     style="background: rgba(0,0,0,0.02); color: var(--dash-text-secondary)">
                                    {{ $application->message }}
                                </div>
                            @endif

                            {{-- CV Download --}}
                            @if($application->getFirstMediaUrl('cv'))
                                <a href="{{ $application->getFirstMediaUrl('cv') }}"
                                   target="_blank"
                                   rel="noopener"
                                   class="inline-flex items-center gap-1.5 text-xs font-medium mt-2 px-2.5 py-1.5 rounded-lg transition-colors hover:opacity-80"
                                   style="background: rgba(var(--portal-primary-rgb, 59 130 246), 0.08); color: var(--portal-primary);">
                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                                    </svg>
                                    Lebenslauf herunterladen
                                </a>
                            @endif
                        </div>

                        {{-- Status Actions --}}
                        <div class="flex items-center gap-2 shrink-0 flex-wrap" role="group" aria-label="Status-Aktionen">
                            @if($application->status === 'pending')
                                <form action="{{ route('portal.owner.jobs.applications.status', [$job->id, $application->id]) }}" method="POST">
                                    @csrf
                                    <input type="hidden" name="status" value="reviewed">
                                    <button type="submit" class="dash-btn dash-btn-sm dash-btn-secondary"
                                            title="Als gelesen markieren"
                                            aria-label="Bewerbung als gelesen markieren">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                        </svg>
                                    </button>
                                </form>
                            @endif

                            @if(in_array($application->status, ['pending', 'reviewed']))
                                <a href="mailto:{{ $application->applicant_email }}?subject=Bewerbung: {{ urlencode($job->title) }}"
                                   class="dash-btn dash-btn-sm dash-btn-primary"
                                   title="Per E-Mail antworten"
                                   aria-label="{{ $application->applicant_name }} per E-Mail antworten">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/>
                                    </svg>
                                </a>

                                <form action="{{ route('portal.owner.jobs.applications.status', [$job->id, $application->id]) }}" method="POST">
                                    @csrf
                                    <input type="hidden" name="status" value="contacted">
                                    <button type="submit" class="dash-btn dash-btn-sm"
                                            style="background: var(--dash-success); color: white;"
                                            title="Als kontaktiert markieren">
                                        Kontaktiert
                                    </button>
                                </form>
                            @endif

                            @if($application->status !== 'rejected')
                                <form action="{{ route('portal.owner.jobs.applications.status', [$job->id, $application->id]) }}" method="POST">
                                    @csrf
                                    <input type="hidden" name="status" value="rejected">
                                    <button type="submit" class="dash-btn dash-btn-sm"
                                            style="color: var(--dash-danger); border-color: rgba(220, 38, 38, 0.2);"
                                            title="Absagen"
                                            aria-label="{{ $application->applicant_name }} absagen">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                                        </svg>
                                    </button>
                                </form>
                            @endif
                        </div>
                    </div>
                </article>
            @endforeach
        </div>

        {{-- Pagination --}}
        @if($applications->hasPages())
            <div class="dash-pagination mt-6">
                {{ $applications->links() }}
            </div>
        @endif
    @endif
@endsection
