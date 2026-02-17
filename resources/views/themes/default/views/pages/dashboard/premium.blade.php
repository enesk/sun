@extends('layouts.dashboard')

@section('title', 'Premium')

@section('content')
    <div class="mb-6">
        <h1 class="text-xl sm:text-2xl font-bold text-base-content">Premium</h1>
        <p class="text-sm text-base-content/60 mt-1">Mehr Sichtbarkeit und Funktionen für Ihr Unternehmen.</p>
    </div>

    @if($company->is_premium)
        {{-- Active Premium State --}}
        <div class="card-portal border-2 border-portal-accent mb-6">
            <div class="flex items-center gap-3 mb-4">
                <div class="w-12 h-12 rounded-full bg-portal-accent-light flex items-center justify-center">
                    <svg class="w-6 h-6 text-portal-accent-dark" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/>
                    </svg>
                </div>
                <div>
                    <h2 class="text-lg font-bold text-portal-accent-dark">Premium aktiv</h2>
                    <p class="text-sm text-base-content/60">Sie nutzen alle Premium-Vorteile.</p>
                </div>
            </div>
        </div>
    @else
        {{-- Upgrade CTA —  Loss Aversion Pattern (Rathana's Spec) --}}
        <div class="bg-portal-gradient rounded-2xl p-6 sm:p-8 text-white mb-8">
            <div class="max-w-2xl">
                <h2 class="text-xl sm:text-2xl font-bold mb-2">Werden Sie sichtbarer</h2>
                <p class="text-white/80 text-sm sm:text-base mb-6">
                    Premium-Einträge erhalten durchschnittlich 3x mehr Aufrufe als kostenlose Einträge.
                </p>
                <button type="button" class="btn bg-white text-portal-primary-dark hover:bg-white/90 font-semibold" disabled>
                    Upgrade starten (bald verfügbar)
                </button>
            </div>
        </div>

        {{-- Feature Comparison --}}
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-8">
            {{-- Free Plan --}}
            <div class="card-portal border border-base-200">
                <div class="mb-4">
                    <span class="badge badge-sm bg-base-200 text-base-content/60 border-0 mb-2">Aktueller Plan</span>
                    <h3 class="text-lg font-bold text-base-content">Kostenlos</h3>
                    <p class="text-2xl font-bold text-base-content mt-1">0 € <span class="text-sm font-normal text-base-content/50">/ Monat</span></p>
                </div>
                <ul class="space-y-2.5">
                    @foreach([
                        'Firmeneintrag mit Basisdaten',
                        'Sichtbar im Verzeichnis',
                        'Bewertungen empfangen',
                        'Kontaktdaten anzeigen',
                    ] as $feature)
                        <li class="flex items-start gap-2 text-sm">
                            <svg class="w-4 h-4 text-green-500 shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                            </svg>
                            <span class="text-base-content/70">{{ $feature }}</span>
                        </li>
                    @endforeach
                </ul>
            </div>

            {{-- Premium Plan --}}
            <div class="card-portal border-2 border-portal-accent relative">
                <div class="absolute -top-3 left-4">
                    <span class="badge badge-sm badge-portal-accent">Empfohlen</span>
                </div>
                <div class="mb-4">
                    <h3 class="text-lg font-bold text-base-content flex items-center gap-2">
                        Premium
                        <svg class="w-5 h-5 text-portal-accent" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 3v4M3 5h4M6 17v4m-2-2h4m5-16l2.286 6.857L21 12l-5.714 2.143L13 21l-2.286-6.857L5 12l5.714-2.143L13 3z"/>
                        </svg>
                    </h3>
                    <p class="text-2xl font-bold text-base-content mt-1">— € <span class="text-sm font-normal text-base-content/50">/ Monat</span></p>
                    <p class="text-xs text-base-content/40 mt-1">Preis wird in Kürze bekannt gegeben</p>
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
                            <svg class="w-4 h-4 text-portal-accent shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                            </svg>
                            <span class="text-base-content">{{ $feature }}</span>
                        </li>
                    @endforeach
                </ul>

                <button type="button" class="btn btn-portal-accent w-full mt-6" disabled>
                    Bald verfügbar
                </button>
            </div>
        </div>

        {{-- Ohne Premium verlieren Sie... (Loss Aversion) --}}
        <div class="card-portal bg-base-200/30 border border-base-200">
            <h3 class="text-sm font-semibold text-base-content mb-3">Was Sie ohne Premium verpassen</h3>
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                <div class="flex items-center gap-2 text-sm">
                    <svg class="w-4 h-4 text-red-400 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 17h8m0 0V9m0 8l-8-8-4 4-6-6"/>
                    </svg>
                    <span class="text-base-content/60">~3x weniger Sichtbarkeit</span>
                </div>
                <div class="flex items-center gap-2 text-sm">
                    <svg class="w-4 h-4 text-red-400 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636"/>
                    </svg>
                    <span class="text-base-content/60">Keine Statistiken</span>
                </div>
                <div class="flex items-center gap-2 text-sm">
                    <svg class="w-4 h-4 text-red-400 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                    </svg>
                    <span class="text-base-content/60">Keine Bildergalerie</span>
                </div>
            </div>
        </div>
    @endif
@endsection
