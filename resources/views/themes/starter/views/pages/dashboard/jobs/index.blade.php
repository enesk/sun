@extends('layouts.dashboard')

@section('title', 'Stellenanzeigen')

@section('content')
    <div class="dash-page-header">
        <div>
            <h1 class="dash-page-title">Stellenanzeigen</h1>
            <p class="dash-page-subtitle">Verwalten Sie Ihre Stellenanzeigen für {{ $company->name }}.</p>
        </div>
        @if($canCreate)
            <a href="{{ route('portal.owner.jobs.create') }}" class="dash-btn dash-btn-primary">
                <svg class="w-4 h-4 mr-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                </svg>
                Neue Stelle
            </a>
        @endif
    </div>

    {{-- Limit-Hinweis --}}
    @if(!$canCreate && $activeJobs->isNotEmpty())
        <div class="dash-card dash-card-padded mb-4" style="background-color: rgba(var(--portal-accent-rgb, 245 158 11), 0.06); border: 1px solid rgba(var(--portal-accent-rgb, 245 158 11), 0.15);">
            <div class="flex items-center gap-3">
                <svg class="w-5 h-5 shrink-0" style="color: var(--portal-accent)" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                </svg>
                <p class="text-sm" style="color: var(--dash-text-secondary)">
                    Sie haben {{ $activeJobs->count() }} von {{ \App\Models\Portal\Job::MAX_ACTIVE_PER_COMPANY }} aktiven Stellenanzeigen. Deaktivieren Sie eine bestehende Stelle, um eine neue zu erstellen.
                </p>
            </div>
        </div>
    @endif

    {{-- Aktive Stellenanzeigen --}}
    <div class="mb-8">
        <h2 class="text-sm font-semibold uppercase tracking-wider mb-3" style="color: var(--dash-text-muted)">
            Aktive Stellen ({{ $activeJobs->count() }})
        </h2>

        @if($activeJobs->isEmpty())
            <div class="dash-card">
                <div class="dash-empty" style="padding: 3rem 1.5rem;">
                    <svg class="w-16 h-16 mx-auto mb-4" style="color: var(--dash-text-muted); opacity: 0.3;" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M21 13.255A23.931 23.931 0 0112 15c-3.183 0-6.22-.62-9-1.745M16 6V4a2 2 0 00-2-2h-4a2 2 0 00-2 2v2m4 6h.01M5 20h14a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/>
                    </svg>
                    <h3 class="dash-empty-title" style="font-size: 1.125rem;">Keine aktiven Stellenanzeigen</h3>
                    <p class="dash-empty-description" style="max-width: 24rem;">
                        Erstellen Sie Ihre erste Stellenanzeige und finden Sie qualifizierte Bewerber aus Ihrer Region.
                    </p>
                    @if($canCreate)
                        <a href="{{ route('portal.owner.jobs.create') }}" class="dash-btn dash-btn-primary dash-btn-sm mt-4">
                            Erste Stelle erstellen
                        </a>
                    @endif
                </div>
            </div>
        @else
            <div class="space-y-3">
                @foreach($activeJobs as $job)
                    @include('pages.dashboard.jobs._job-card', ['job' => $job])
                @endforeach
            </div>
        @endif
    </div>

    {{-- Abgelaufene Stellenanzeigen --}}
    @if($expiredJobs->isNotEmpty())
        <div>
            <h2 class="text-sm font-semibold uppercase tracking-wider mb-3" style="color: var(--dash-text-muted)">
                Abgelaufen / Inaktiv ({{ $expiredJobs->count() }})
            </h2>
            <div class="space-y-3">
                @foreach($expiredJobs as $job)
                    @include('pages.dashboard.jobs._job-card', ['job' => $job, 'expired' => true])
                @endforeach
            </div>
        </div>
    @endif
@endsection
