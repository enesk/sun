@extends('layouts.verwaltung')

@section('title', 'Stellenanzeigen verwalten — Verwaltung')

@section('content')
    {{-- Page Header --}}
    <div class="dash-page-header">
        <div>
            <h1 class="dash-page-title">Stellenanzeigen</h1>
            <p class="dash-page-subtitle">Jobs prüfen, aktivieren, deaktivieren und löschen</p>
        </div>
    </div>

    {{-- Job Table (Livewire) --}}
    @livewire('verwaltung.job-table')
@endsection
