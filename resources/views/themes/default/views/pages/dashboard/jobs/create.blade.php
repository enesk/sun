@extends('layouts.dashboard')

@section('title', 'Neue Stellenanzeige')

@section('content')
    <div class="dash-page-header">
        <div>
            <h1 class="dash-page-title">Neue Stellenanzeige</h1>
            <p class="dash-page-subtitle">Erstellen Sie eine neue Stellenanzeige für {{ $company->name }}.</p>
        </div>
        <a href="{{ route('portal.owner.jobs.index') }}" class="dash-btn dash-btn-secondary dash-btn-sm">
            <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
            </svg>
            Zurück
        </a>
    </div>

    @livewire('portal.dashboard.owner-job-form')
@endsection
