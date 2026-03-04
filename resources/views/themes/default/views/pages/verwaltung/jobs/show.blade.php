@extends('layouts.verwaltung')

@section('title', $job->title . ' — Stellenanzeige — Verwaltung')

@section('content')
    {{-- Page Header --}}
    <div class="dash-page-header">
        <div>
            <div class="flex items-center gap-2 mb-1">
                <a href="{{ route('verwaltung.jobs.index') }}" class="dash-btn dash-btn-sm dash-btn-ghost">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
                    </svg>
                    Zurück
                </a>
            </div>
            <div class="flex flex-wrap items-center gap-2">
                <h1 class="dash-page-title">{{ $job->title }}</h1>
                @if($job->is_active && $job->expires_at && $job->expires_at->isFuture())
                    <span class="dash-badge dash-badge-success">Aktiv</span>
                @elseif($job->is_active && $job->expires_at && $job->expires_at->isPast())
                    <span class="dash-badge dash-badge-warning">Abgelaufen</span>
                @else
                    <span class="dash-badge dash-badge-danger">Inaktiv</span>
                @endif
            </div>
            <p class="dash-page-subtitle">
                {{ $job->company->name ?? 'Unbekannte Firma' }}
                @if($job->company && $job->company->is_premium)
                    <span class="dash-badge dash-badge-info" style="font-size: 0.625rem; padding: 0 0.375rem;">Premium</span>
                @endif
            </p>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-4">
        {{-- Haupt-Content (2/3) --}}
        <div class="lg:col-span-2 space-y-4">
            {{-- Stellenbeschreibung --}}
            <div class="dash-card">
                <div class="dash-card-header">
                    <h2 class="dash-card-title">Stellenbeschreibung</h2>
                </div>
                <div class="dash-card-body">
                    <div class="prose prose-sm max-w-none" style="color: var(--dash-text-primary);">
                        {!! nl2br(e($job->description)) !!}
                    </div>
                </div>
            </div>

            {{-- Anforderungen --}}
            @if($job->requirements)
                <div class="dash-card">
                    <div class="dash-card-header">
                        <h2 class="dash-card-title">Anforderungen</h2>
                    </div>
                    <div class="dash-card-body">
                        <div class="prose prose-sm max-w-none" style="color: var(--dash-text-primary);">
                            {!! nl2br(e($job->requirements)) !!}
                        </div>
                    </div>
                </div>
            @endif

            {{-- Benefits --}}
            @if($job->benefits)
                <div class="dash-card">
                    <div class="dash-card-header">
                        <h2 class="dash-card-title">Benefits</h2>
                    </div>
                    <div class="dash-card-body">
                        <div class="prose prose-sm max-w-none" style="color: var(--dash-text-primary);">
                            {!! nl2br(e($job->benefits)) !!}
                        </div>
                    </div>
                </div>
            @endif

            {{-- Bewerbungen --}}
            <div class="dash-card">
                <div class="dash-card-header">
                    <h2 class="dash-card-title">
                        Bewerbungen
                        @if($job->applications_count > 0)
                            <span class="dash-badge dash-badge-info" style="margin-left: 0.5rem;">{{ $job->applications_count }}</span>
                        @endif
                    </h2>
                </div>
                <div class="dash-card-body">
                    @forelse($job->applications as $application)
                        <div class="py-3 {{ !$loop->last ? 'border-b' : '' }}" style="border-color: var(--dash-border, #e2e8f0);">
                            <div class="flex items-start justify-between gap-3">
                                <div class="min-w-0 flex-1">
                                    <div class="flex flex-wrap items-center gap-2 mb-1">
                                        <span class="text-sm font-semibold" style="color: var(--dash-text-primary);">
                                            {{ $application->applicant_name }}
                                        </span>
                                        @php
                                            $statusColors = [
                                                'pending' => 'dash-badge-warning',
                                                'reviewed' => 'dash-badge-info',
                                                'contacted' => 'dash-badge-success',
                                                'rejected' => 'dash-badge-danger',
                                            ];
                                        @endphp
                                        <span class="dash-badge {{ $statusColors[$application->status] ?? '' }}">
                                            {{ $application->status_label }}
                                        </span>
                                    </div>
                                    <div class="flex flex-wrap items-center gap-x-3 gap-y-1 text-xs" style="color: var(--dash-text-muted);">
                                        <a href="mailto:{{ $application->applicant_email }}" class="hover:underline">
                                            {{ $application->applicant_email }}
                                        </a>
                                        @if($application->applicant_phone)
                                            <span>{{ $application->applicant_phone }}</span>
                                        @endif
                                        <span>{{ $application->created_at->format('d.m.Y H:i') }}</span>
                                        @if($application->has_cv)
                                            <a href="{{ $application->cv_url }}" target="_blank"
                                               class="inline-flex items-center gap-1 hover:underline"
                                               style="color: var(--portal-primary, #3b82f6);">
                                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.5">
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5M16.5 12L12 16.5m0 0L7.5 12m4.5 4.5V3"/>
                                                </svg>
                                                Lebenslauf
                                            </a>
                                        @endif
                                    </div>
                                    @if($application->message)
                                        <p class="mt-2 text-sm" style="color: var(--dash-text-secondary);">
                                            {{ Str::limit($application->message, 300) }}
                                        </p>
                                    @endif
                                </div>
                            </div>
                        </div>
                    @empty
                        <div class="dash-empty">
                            <svg class="dash-empty-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M21.75 6.75v10.5a2.25 2.25 0 01-2.25 2.25h-15a2.25 2.25 0 01-2.25-2.25V6.75m19.5 0A2.25 2.25 0 0019.5 4.5h-15a2.25 2.25 0 00-2.25 2.25m19.5 0v.243a2.25 2.25 0 01-1.07 1.916l-7.5 4.615a2.25 2.25 0 01-2.36 0L3.32 8.91a2.25 2.25 0 01-1.07-1.916V6.75"/>
                            </svg>
                            <p class="dash-empty-title">Noch keine Bewerbungen</p>
                        </div>
                    @endforelse
                </div>
            </div>
        </div>

        {{-- Sidebar (1/3) --}}
        <div class="space-y-4">
            {{-- Meta-Daten --}}
            <div class="dash-card">
                <div class="dash-card-header">
                    <h2 class="dash-card-title">Details</h2>
                </div>
                <div class="dash-card-body space-y-3">
                    <div>
                        <span class="text-xs font-medium" style="color: var(--dash-text-muted);">Beschäftigungsart</span>
                        <p class="text-sm" style="color: var(--dash-text-primary);">{{ $job->employment_type_label }}</p>
                    </div>

                    @if($job->location_display)
                        <div>
                            <span class="text-xs font-medium" style="color: var(--dash-text-muted);">Standort</span>
                            <p class="text-sm" style="color: var(--dash-text-primary);">{{ $job->location_display }}</p>
                        </div>
                    @endif

                    @if($job->salary_display)
                        <div>
                            <span class="text-xs font-medium" style="color: var(--dash-text-muted);">Gehalt</span>
                            <p class="text-sm" style="color: var(--dash-text-primary);">{{ $job->salary_display }}</p>
                        </div>
                    @endif

                    @if($job->application_deadline)
                        <div>
                            <span class="text-xs font-medium" style="color: var(--dash-text-muted);">Bewerbungsfrist</span>
                            <p class="text-sm" style="color: var(--dash-text-primary);">{{ $job->application_deadline->format('d.m.Y') }}</p>
                        </div>
                    @endif

                    <div>
                        <span class="text-xs font-medium" style="color: var(--dash-text-muted);">Erstellt</span>
                        <p class="text-sm" style="color: var(--dash-text-primary);">{{ $job->created_at->format('d.m.Y H:i') }}</p>
                    </div>

                    @if($job->published_at)
                        <div>
                            <span class="text-xs font-medium" style="color: var(--dash-text-muted);">Veröffentlicht</span>
                            <p class="text-sm" style="color: var(--dash-text-primary);">{{ $job->published_at->format('d.m.Y H:i') }}</p>
                        </div>
                    @endif

                    @if($job->expires_at)
                        <div>
                            <span class="text-xs font-medium" style="color: var(--dash-text-muted);">Läuft ab</span>
                            <p class="text-sm" style="{{ $job->expires_at->isPast() ? 'color: var(--dash-danger);' : 'color: var(--dash-text-primary);' }}">
                                {{ $job->expires_at->format('d.m.Y H:i') }}
                                @if($job->expires_at->isPast())
                                    (abgelaufen)
                                @else
                                    ({{ $job->days_remaining }} Tage)
                                @endif
                            </p>
                        </div>
                    @endif

                    <div>
                        <span class="text-xs font-medium" style="color: var(--dash-text-muted);">Aufrufe</span>
                        <p class="text-sm" style="color: var(--dash-text-primary);">{{ number_format($job->views_count ?? 0, 0, ',', '.') }}</p>
                    </div>
                </div>
            </div>

            {{-- Firma --}}
            @if($job->company)
                <div class="dash-card">
                    <div class="dash-card-header">
                        <h2 class="dash-card-title">Firma</h2>
                    </div>
                    <div class="dash-card-body">
                        <p class="text-sm font-semibold" style="color: var(--dash-text-primary);">{{ $job->company->name }}</p>
                        @if($job->company->city)
                            <p class="text-xs" style="color: var(--dash-text-muted);">{{ $job->company->city->name }}</p>
                        @endif
                        @if($job->company->is_premium)
                            <span class="dash-badge dash-badge-info mt-1">Premium</span>
                        @endif
                    </div>
                </div>
            @endif

            {{-- Slug / URL --}}
            <div class="dash-card">
                <div class="dash-card-header">
                    <h2 class="dash-card-title">URL-Slug</h2>
                </div>
                <div class="dash-card-body">
                    <code class="text-xs break-all" style="color: var(--dash-text-secondary);">{{ $job->slug }}</code>
                </div>
            </div>
        </div>
    </div>
@endsection
