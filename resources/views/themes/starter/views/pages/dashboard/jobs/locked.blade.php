@extends('layouts.dashboard')

@section('title', 'Stellenanzeigen — Premium')

@section('content')
    <div class="dash-page-header">
        <div>
            <h1 class="dash-page-title">Stellenanzeigen</h1>
            <p class="dash-page-subtitle">Finden Sie qualifizierte Bewerber aus Ihrer Region.</p>
        </div>
    </div>

    {{-- Premium Lock Card --}}
    <div class="dash-card" style="overflow: hidden;">
        {{-- Blurred Preview --}}
        <div class="pointer-events-none select-none" style="filter: blur(3px); opacity: 0.35; padding: 1.5rem;" aria-hidden="true">
            <div class="flex items-center justify-between mb-4">
                <div>
                    <div class="h-4 rounded" style="width: 180px; background: var(--dash-text-muted); opacity: 0.3;"></div>
                    <div class="h-3 rounded mt-2" style="width: 120px; background: var(--dash-text-muted); opacity: 0.2;"></div>
                </div>
                <div class="h-8 rounded-lg" style="width: 100px; background: var(--portal-primary); opacity: 0.3;"></div>
            </div>
            <div class="space-y-3">
                @for($i = 0; $i < 2; $i++)
                    <div class="p-4 rounded-lg" style="border: 1px solid var(--dash-border);">
                        <div class="flex items-center gap-2">
                            <div class="h-4 rounded" style="width: {{ 140 + $i * 30 }}px; background: var(--dash-text-muted); opacity: 0.3;"></div>
                            <div class="h-5 rounded-full" style="width: 60px; background: var(--portal-primary); opacity: 0.2;"></div>
                        </div>
                        <div class="flex items-center gap-3 mt-2">
                            <div class="h-3 rounded" style="width: 80px; background: var(--dash-text-muted); opacity: 0.2;"></div>
                            <div class="h-3 rounded" style="width: 100px; background: var(--dash-text-muted); opacity: 0.2;"></div>
                        </div>
                    </div>
                @endfor
            </div>
        </div>

        {{-- Lock Overlay --}}
        <div style="padding: 2rem 1.5rem; text-align: center; margin-top: -2rem; position: relative; z-index: 1;">
            <div class="mx-auto mb-4" style="width: 3.5rem; height: 3.5rem; border-radius: 50%; display: flex; align-items: center; justify-content: center; background-color: rgba(var(--portal-accent-rgb, 245 158 11), 0.12);">
                <svg class="w-6 h-6" style="color: var(--portal-accent)" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/>
                </svg>
            </div>

            <h2 class="text-lg font-semibold mb-1" style="color: var(--dash-text-primary)">Stellenanzeigen schalten</h2>
            <p class="text-sm mb-5 mx-auto" style="color: var(--dash-text-secondary); max-width: 28rem;">
                Veröffentlichen Sie Stellenanzeigen direkt auf Ihrem Firmenprofil und in der Jobbörse — finden Sie qualifizierte Bewerber aus Ihrer Region.
            </p>

            {{-- Feature List --}}
            <div class="flex flex-col sm:flex-row items-center justify-center gap-3 sm:gap-5 mb-6">
                <span class="flex items-center gap-1.5 text-sm" style="color: var(--dash-text-secondary)">
                    <svg class="w-4 h-4 shrink-0" style="color: var(--dash-success)" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                    </svg>
                    1 aktive Stellenanzeige
                </span>
                <span class="flex items-center gap-1.5 text-sm" style="color: var(--dash-text-secondary)">
                    <svg class="w-4 h-4 shrink-0" style="color: var(--dash-success)" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                    </svg>
                    In-App Bewerbungen
                </span>
                <span class="flex items-center gap-1.5 text-sm" style="color: var(--dash-text-secondary)">
                    <svg class="w-4 h-4 shrink-0" style="color: var(--dash-success)" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                    </svg>
                    30 Tage sichtbar
                </span>
            </div>

            <a href="{{ route('portal.owner.premium') }}" class="dash-btn dash-btn-primary">
                <svg class="w-4 h-4 mr-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 3v4M3 5h4M6 17v4m-2-2h4m5-16l2.286 6.857L21 12l-5.714 2.143L13 21l-2.286-6.857L5 12l5.714-2.143L13 3z"/>
                </svg>
                Jetzt Premium werden — ab 9,90 €/Monat
            </a>
        </div>
    </div>
@endsection
