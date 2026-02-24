@extends('layouts.dashboard')

@section('title', 'Statistiken')

@section('content')
    <div class="dash-page-header">
        <div>
            <h1 class="dash-page-title">Statistiken</h1>
            <p class="dash-page-subtitle">Wie Kunden Ihren Eintrag finden und nutzen.</p>
        </div>
    </div>

    @if($company->is_premium)
        {{-- Premium: Full Stats (Coming Soon — Platzhalter für echte Daten) --}}
        <div class="dash-card dash-card-padded">
            <div class="dash-empty" style="padding: 4rem 1.5rem;">
                <div class="w-16 h-16 mx-auto mb-4 rounded-full flex items-center justify-center" style="background-color: rgba(var(--portal-primary-rgb, 59 130 246), 0.08);">
                    <svg class="w-8 h-8" style="color: var(--portal-primary)" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/>
                    </svg>
                </div>
                <h2 class="text-lg font-semibold mb-2" style="color: var(--dash-text-primary)">Statistiken kommen bald</h2>
                <p class="text-sm max-w-md mx-auto" style="color: var(--dash-text-muted)">
                    Als Premium-Nutzer erhalten Sie bald detaillierte Statistiken mit Wochen-Trends, Besucherherkunft und Suchplatzierungen.
                </p>

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
            </div>
        </div>
    @else
        {{-- Free User: Soft-Lock mit geblurrten Mock-Daten --}}

        {{-- Basis-Stats (sichtbar für alle) --}}
        <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 sm:gap-4 mb-6">
            <div class="dash-stat-card">
                <div class="flex items-center gap-2 mb-1">
                    <svg class="w-4 h-4" style="color: var(--dash-text-muted)" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                    </svg>
                    <span class="dash-stat-label">Aufrufe</span>
                </div>
                <span class="dash-stat-value">—</span>
                <span class="dash-stat-sub">Gesamt</span>
            </div>

            <div class="dash-stat-card">
                <div class="flex items-center gap-2 mb-1">
                    <svg class="w-4 h-4" style="color: var(--dash-text-muted)" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"/>
                    </svg>
                    <span class="dash-stat-label">Kontakte</span>
                </div>
                <span class="dash-stat-value">—</span>
                <span class="dash-stat-sub">Gesamt</span>
            </div>

            <div class="dash-stat-card">
                <div class="flex items-center gap-2 mb-1">
                    <svg class="w-4 h-4 text-portal-accent" fill="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                        <path d="M11.049 2.927c.3-.921 1.603-.921 1.902 0l1.519 4.674a1 1 0 00.95.69h4.915c.969 0 1.371 1.24.588 1.81l-3.976 2.888a1 1 0 00-.363 1.118l1.518 4.674c.3.922-.755 1.688-1.538 1.118l-3.976-2.888a1 1 0 00-1.176 0l-3.976 2.888c-.783.57-1.838-.197-1.538-1.118l1.518-4.674a1 1 0 00-.363-1.118l-3.976-2.888c-.784-.57-.38-1.81.588-1.81h4.914a1 1 0 00.951-.69l1.519-4.674z"/>
                    </svg>
                    <span class="dash-stat-label">Rating</span>
                </div>
                <span class="dash-stat-value">{{ $company->rating > 0 ? number_format($company->rating, 1) : '—' }}</span>
                <span class="dash-stat-sub">{{ $company->rating_count }} {{ $company->rating_count === 1 ? 'Bewertung' : 'Bewertungen' }}</span>
            </div>

            <div class="dash-stat-card">
                <div class="flex items-center gap-2 mb-1">
                    <svg class="w-4 h-4" style="color: var(--dash-text-muted)" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                    </svg>
                    <span class="dash-stat-label">Suche</span>
                </div>
                <span class="dash-stat-value">—</span>
                <span class="dash-stat-sub">Erscheinungen</span>
            </div>
        </div>

        {{-- Geblurrte Premium-Statistiken mit Lock-Overlay --}}
        <div class="dash-card relative overflow-hidden" style="min-height: 320px;">
            {{-- Geblurrter Mock-Content --}}
            <div class="pointer-events-none select-none p-6" style="filter: blur(5px); opacity: 0.5;" aria-hidden="true">
                {{-- Mock Trend-Titel --}}
                <div class="flex items-center justify-between mb-4">
                    <span class="text-sm font-semibold" style="color: var(--dash-text-primary)">Profilaufrufe — Letzte 30 Tage</span>
                    <span class="text-xs px-2 py-0.5 rounded" style="background-color: rgba(22, 163, 74, 0.1); color: #16a34a;">+24%</span>
                </div>

                {{-- Mock Chart (CSS bars) --}}
                <div class="flex items-end gap-1.5" style="height: 120px;">
                    @foreach([35, 42, 28, 55, 48, 62, 45, 70, 58, 75, 52, 80, 65, 88, 72, 90, 68, 85, 78, 92, 70, 95, 82, 98, 75, 88, 90, 105, 95, 110] as $val)
                        <div class="flex-1 rounded-t" style="height: {{ ($val / 110) * 100 }}%; background-color: var(--portal-primary); opacity: 0.6;"></div>
                    @endforeach
                </div>

                {{-- Mock Stats Grid --}}
                <div class="grid grid-cols-3 gap-4 mt-6">
                    <div>
                        <div class="text-lg font-bold" style="color: var(--dash-text-primary)">1.247</div>
                        <div class="text-xs" style="color: var(--dash-text-muted)">Profilaufrufe</div>
                    </div>
                    <div>
                        <div class="text-lg font-bold" style="color: var(--dash-text-primary)">89</div>
                        <div class="text-xs" style="color: var(--dash-text-muted)">Kontaktklicks</div>
                    </div>
                    <div>
                        <div class="text-lg font-bold" style="color: var(--dash-text-primary)">342</div>
                        <div class="text-xs" style="color: var(--dash-text-muted)">Suchanfragen</div>
                    </div>
                </div>

                {{-- Mock Herkunft --}}
                <div class="mt-6">
                    <span class="text-sm font-semibold" style="color: var(--dash-text-primary)">Top Suchbegriffe</span>
                    <div class="space-y-2 mt-3">
                        <div class="flex items-center justify-between">
                            <span class="text-sm" style="color: var(--dash-text-secondary)">maler berlin</span>
                            <span class="text-sm font-medium" style="color: var(--dash-text-primary)">124</span>
                        </div>
                        <div class="flex items-center justify-between">
                            <span class="text-sm" style="color: var(--dash-text-secondary)">malermeister kreuzberg</span>
                            <span class="text-sm font-medium" style="color: var(--dash-text-primary)">87</span>
                        </div>
                        <div class="flex items-center justify-between">
                            <span class="text-sm" style="color: var(--dash-text-secondary)">wohnung streichen</span>
                            <span class="text-sm font-medium" style="color: var(--dash-text-primary)">53</span>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Lock-Overlay --}}
            <div class="absolute inset-0 flex items-center justify-center" style="background: linear-gradient(180deg, rgba(255,255,255,0.3) 0%, rgba(255,255,255,0.85) 40%, rgba(255,255,255,0.95) 100%);">
                <div class="text-center px-6 max-w-sm">
                    <div class="w-14 h-14 mx-auto mb-4 rounded-full flex items-center justify-center" style="background-color: rgba(var(--portal-accent-rgb, 245 158 11), 0.12);">
                        <svg class="w-7 h-7" style="color: var(--portal-accent)" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/>
                        </svg>
                    </div>
                    <h3 class="text-base font-bold mb-1" style="color: var(--dash-text-primary)">Detaillierte Statistiken</h3>
                    <p class="text-sm mb-4" style="color: var(--dash-text-secondary)">
                        Sehen Sie Wochen-Trends, Besucherherkunft und welche Suchbegriffe Kunden zu Ihnen führen.
                    </p>
                    <a href="{{ route('portal.owner.premium') }}"
                       class="dash-btn dash-btn-sm inline-flex items-center gap-1.5"
                       style="background-color: var(--portal-accent); color: white;">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 3v4M3 5h4M6 17v4m-2-2h4m5-16l2.286 6.857L21 12l-5.714 2.143L13 21l-2.286-6.857L5 12l5.714-2.143L13 3z"/>
                        </svg>
                        Premium freischalten — 9,90 €/Monat
                    </a>
                    <p class="text-xs mt-2" style="color: var(--dash-text-muted)">Keine Bindung. Jederzeit kündbar.</p>
                </div>
            </div>
        </div>
    @endif
@endsection
