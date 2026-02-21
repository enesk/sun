@extends('layouts.verwaltung')

@section('title', 'Firmen verwalten — Verwaltung')

@section('content')
    {{-- Page Header --}}
    <div class="dash-page-header">
        <div>
            <h1 class="dash-page-title">Firmen</h1>
            <p class="dash-page-subtitle">Firmeneinträge verwalten, bearbeiten und freigeben</p>
        </div>
        <a href="{{ route('verwaltung.companies.create') }}" class="dash-btn dash-btn-primary">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15"/>
            </svg>
            Neue Firma
        </a>
    </div>

    {{-- Company Table (Livewire) --}}
    @livewire('verwaltung.company-table')
@endsection
