@extends('layouts.dashboard')

@section('title', 'Statistiken')

@section('content')
    <div class="mb-6">
        <h1 class="text-xl sm:text-2xl font-bold text-base-content">Statistiken</h1>
        <p class="text-sm text-base-content/60 mt-1">Wie Kunden Ihren Eintrag finden und nutzen.</p>
    </div>

    {{-- Coming Soon State --}}
    <div class="card-portal text-center py-16">
        <div class="w-16 h-16 mx-auto mb-4 rounded-full bg-portal-primary-light flex items-center justify-center">
            <svg class="w-8 h-8 text-portal-primary" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/>
            </svg>
        </div>
        <h2 class="text-lg font-semibold text-base-content mb-2">Statistiken kommen bald</h2>
        <p class="text-sm text-base-content/50 max-w-md mx-auto">
            Hier sehen Sie bald detaillierte Statistiken zu Ihrem Eintrag — Profilaufrufe, Kontaktklicks und Suchplatzierungen.
        </p>

        {{-- Preview of what's coming --}}
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 mt-8 max-w-lg mx-auto">
            <div class="p-4 rounded-lg bg-base-200/50">
                <div class="text-2xl font-bold text-base-content/20">—</div>
                <div class="text-xs text-base-content/30 mt-1">Profilaufrufe</div>
            </div>
            <div class="p-4 rounded-lg bg-base-200/50">
                <div class="text-2xl font-bold text-base-content/20">—</div>
                <div class="text-xs text-base-content/30 mt-1">Kontaktklicks</div>
            </div>
            <div class="p-4 rounded-lg bg-base-200/50">
                <div class="text-2xl font-bold text-base-content/20">—</div>
                <div class="text-xs text-base-content/30 mt-1">Suchanfragen</div>
            </div>
        </div>

        @if(!$company->is_premium)
            <div class="mt-6 p-3 rounded-lg bg-portal-accent-light/30 border border-portal-accent/20 max-w-sm mx-auto">
                <p class="text-xs text-portal-accent-dark">
                    <strong>Premium-Vorteil:</strong> Erweiterte Statistiken und Trendanalysen sind Teil des Premium-Pakets.
                </p>
            </div>
        @endif
    </div>
@endsection
