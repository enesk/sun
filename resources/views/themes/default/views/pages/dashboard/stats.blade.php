@extends('layouts.dashboard')

@section('title', 'Statistiken')

@section('content')
    <div class="dash-page-header">
        <div>
            <h1 class="dash-page-title">Statistiken</h1>
            <p class="dash-page-subtitle">Wie Kunden Ihren Eintrag finden und nutzen.</p>
        </div>
    </div>

    {{-- Coming Soon State --}}
    <div class="dash-card dash-card-padded">
        <div class="dash-empty" style="padding: 4rem 1.5rem;">
            <div class="w-16 h-16 mx-auto mb-4 rounded-full flex items-center justify-center" style="background-color: rgba(var(--portal-primary-rgb, 59 130 246), 0.08);">
                <svg class="w-8 h-8" style="color: var(--portal-primary)" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/>
                </svg>
            </div>
            <h2 class="text-lg font-semibold mb-2" style="color: var(--dash-text-primary)">Statistiken kommen bald</h2>
            <p class="text-sm max-w-md mx-auto" style="color: var(--dash-text-muted)">
                Hier sehen Sie bald detaillierte Statistiken zu Ihrem Eintrag — Profilaufrufe, Kontaktklicks und Suchplatzierungen.
            </p>

            {{-- Preview of what's coming --}}
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 mt-8 max-w-lg mx-auto">
                <div class="p-4 rounded-lg" style="background-color: var(--dash-bg);">
                    <div class="text-2xl font-bold" style="color: var(--dash-text-muted); opacity: 0.5;">—</div>
                    <div class="text-xs mt-1" style="color: var(--dash-text-muted)">Profilaufrufe</div>
                </div>
                <div class="p-4 rounded-lg" style="background-color: var(--dash-bg);">
                    <div class="text-2xl font-bold" style="color: var(--dash-text-muted); opacity: 0.5;">—</div>
                    <div class="text-xs mt-1" style="color: var(--dash-text-muted)">Kontaktklicks</div>
                </div>
                <div class="p-4 rounded-lg" style="background-color: var(--dash-bg);">
                    <div class="text-2xl font-bold" style="color: var(--dash-text-muted); opacity: 0.5;">—</div>
                    <div class="text-xs mt-1" style="color: var(--dash-text-muted)">Suchanfragen</div>
                </div>
            </div>

            @if(!$company->is_premium)
                <div class="mt-6 p-3 rounded-lg max-w-sm mx-auto" style="background-color: rgba(var(--portal-accent-rgb, 245 158 11), 0.06); border: 1px solid rgba(var(--portal-accent-rgb, 245 158 11), 0.15);">
                    <p class="text-xs" style="color: var(--portal-accent-dark)">
                        <strong>Premium-Vorteil:</strong> Erweiterte Statistiken und Trendanalysen sind Teil des Premium-Pakets.
                    </p>
                </div>
            @endif
        </div>
    </div>
@endsection
