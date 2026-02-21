@extends('layouts.dashboard')

@section('title', 'Premium')

@section('content')
    <div class="dash-page-header">
        <div>
            <h1 class="dash-page-title">Premium</h1>
            <p class="dash-page-subtitle">Mehr Sichtbarkeit und Funktionen für Ihr Unternehmen.</p>
        </div>
    </div>

    @if($company->is_premium)
        {{-- Active Premium State --}}
        <div class="dash-card dash-card-padded mb-6" style="border: 2px solid var(--portal-accent);">
            <div class="flex items-center gap-3 mb-4">
                <div class="w-12 h-12 rounded-full flex items-center justify-center" style="background-color: rgba(var(--portal-accent-rgb, 245 158 11), 0.12);">
                    <svg class="w-6 h-6" style="color: var(--portal-accent-dark)" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/>
                    </svg>
                </div>
                <div>
                    <h2 class="text-lg font-bold" style="color: var(--portal-accent-dark)">Premium aktiv</h2>
                    <p class="text-sm" style="color: var(--dash-text-secondary)">Sie nutzen alle Premium-Vorteile.</p>
                </div>
            </div>
        </div>
    @else
        {{-- Upgrade CTA — Loss Aversion Pattern --}}
        <div class="bg-portal-gradient rounded-2xl p-6 sm:p-8 text-white mb-8">
            <div class="max-w-2xl">
                <h2 class="text-xl sm:text-2xl font-bold mb-2">Werden Sie sichtbarer</h2>
                <p class="text-white/80 text-sm sm:text-base mb-6">
                    Premium-Einträge erhalten durchschnittlich 3x mehr Aufrufe als kostenlose Einträge.
                </p>
                <button type="button" class="dash-btn" style="background: white; color: var(--portal-primary-dark);" disabled>
                    Upgrade starten (bald verfügbar)
                </button>
            </div>
        </div>

        {{-- Feature Comparison --}}
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-8">
            {{-- Free Plan --}}
            <div class="dash-card dash-card-padded">
                <div class="mb-4">
                    <span class="dash-badge dash-badge-neutral mb-2">Aktueller Plan</span>
                    <h3 class="text-lg font-bold" style="color: var(--dash-text-primary)">Kostenlos</h3>
                    <p class="text-2xl font-bold mt-1" style="color: var(--dash-text-primary)">0 € <span class="text-sm font-normal" style="color: var(--dash-text-muted)">/ Monat</span></p>
                </div>
                <ul class="space-y-2.5">
                    @foreach([
                        'Firmeneintrag mit Basisdaten',
                        'Sichtbar im Verzeichnis',
                        'Bewertungen empfangen',
                        'Kontaktdaten anzeigen',
                    ] as $feature)
                        <li class="flex items-start gap-2 text-sm">
                            <svg class="w-4 h-4 shrink-0 mt-0.5" style="color: var(--dash-success)" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                            </svg>
                            <span style="color: var(--dash-text-secondary)">{{ $feature }}</span>
                        </li>
                    @endforeach
                </ul>
            </div>

            {{-- Premium Plan --}}
            <div class="dash-card dash-card-padded relative" style="border: 2px solid var(--portal-accent);">
                <div class="absolute -top-3 left-4">
                    <span class="dash-badge dash-badge-premium">Empfohlen</span>
                </div>
                <div class="mb-4">
                    <h3 class="text-lg font-bold flex items-center gap-2" style="color: var(--dash-text-primary)">
                        Premium
                        <svg class="w-5 h-5" style="color: var(--portal-accent)" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 3v4M3 5h4M6 17v4m-2-2h4m5-16l2.286 6.857L21 12l-5.714 2.143L13 21l-2.286-6.857L5 12l5.714-2.143L13 3z"/>
                        </svg>
                    </h3>
                    <p class="text-2xl font-bold mt-1" style="color: var(--dash-text-primary)">— € <span class="text-sm font-normal" style="color: var(--dash-text-muted)">/ Monat</span></p>
                    <p class="text-xs mt-1" style="color: var(--dash-text-muted)">Preis wird in Kürze bekannt gegeben</p>
                </div>
                <ul class="space-y-2.5">
                    @foreach([
                        'Alles aus dem kostenlosen Plan',
                        'Hervorgehobener Eintrag (Top-Platzierung)',
                        'Erweiterte Statistiken & Trends',
                        'Bildergalerie (bis zu 10 Fotos)',
                        'Logo prominent angezeigt',
                        'Priorität im Suchergebnis',
                        'Verifiziertes Abzeichen',
                        'Kein Werbung auf Ihrem Profil',
                    ] as $feature)
                        <li class="flex items-start gap-2 text-sm">
                            <svg class="w-4 h-4 shrink-0 mt-0.5" style="color: var(--portal-accent)" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                            </svg>
                            <span style="color: var(--dash-text-primary)">{{ $feature }}</span>
                        </li>
                    @endforeach
                </ul>

                <button type="button" class="dash-btn dash-btn-primary w-full mt-6" style="background-color: var(--portal-accent); color: white;" disabled>
                    Bald verfügbar
                </button>
            </div>
        </div>

        {{-- Loss Aversion --}}
        <div class="dash-card dash-card-padded" style="background-color: var(--dash-bg);">
            <h3 class="text-sm font-semibold mb-3" style="color: var(--dash-text-primary)">Was Sie ohne Premium verpassen</h3>
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                <div class="flex items-center gap-2 text-sm">
                    <svg class="w-4 h-4 shrink-0" style="color: var(--dash-danger)" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 17h8m0 0V9m0 8l-8-8-4 4-6-6"/>
                    </svg>
                    <span style="color: var(--dash-text-secondary)">~3x weniger Sichtbarkeit</span>
                </div>
                <div class="flex items-center gap-2 text-sm">
                    <svg class="w-4 h-4 shrink-0" style="color: var(--dash-danger)" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636"/>
                    </svg>
                    <span style="color: var(--dash-text-secondary)">Keine Statistiken</span>
                </div>
                <div class="flex items-center gap-2 text-sm">
                    <svg class="w-4 h-4 shrink-0" style="color: var(--dash-danger)" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                    </svg>
                    <span style="color: var(--dash-text-secondary)">Keine Bildergalerie</span>
                </div>
            </div>
        </div>
    @endif
@endsection
