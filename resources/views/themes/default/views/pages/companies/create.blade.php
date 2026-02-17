@extends('layouts.app')

@section('title', 'Firma eintragen — ' . ($currentTenant->name ?? config('app.name')))
@section('meta_description', 'Tragen Sie Ihr Unternehmen kostenlos ein und werden Sie von Kunden gefunden.')

@section('content')

<div class="eintragen-page">

    @auth
        {{-- Mini-Hero-Banner mit Mesh-Gradient --}}
        <div class="eintragen-hero relative overflow-hidden">
            {{-- Blobs (dezenter als Homepage) --}}
            <div class="hero-blob hero-blob-1 pointer-events-none" aria-hidden="true"></div>
            <div class="hero-blob hero-blob-2 pointer-events-none" aria-hidden="true"></div>

            <div class="relative z-10 max-w-3xl mx-auto px-4 sm:px-6 text-center">
                {{-- Icon + Titel --}}
                <div class="inline-flex items-center justify-center w-14 h-14 rounded-2xl mb-4"
                     style="background: rgba(255,255,255,0.15); backdrop-filter: blur(8px);">
                    <svg class="w-7 h-7 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"
                         style="stroke-dasharray: 100; stroke-dashoffset: 100; animation: draw-icon 1.5s ease-out forwards;">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/>
                    </svg>
                </div>
                <h1 class="text-2xl md:text-3xl font-bold text-white">Machen Sie Ihre Firma sichtbar</h1>
                <p class="text-white/70 mt-2 text-sm md:text-base">Kostenlos &middot; In 3 Minuten fertig</p>
            </div>
        </div>

        {{-- Glass-Card mit Wizard (überlappt Hero) --}}
        <div class="max-w-3xl mx-auto px-4 sm:px-6 -mt-6 relative z-10 pb-8">
            {{-- Wizard Glass Card --}}
            <div class="wizard-card">
                <livewire:portal.company-registration-wizard />
            </div>

            {{-- Trust-Bar --}}
            <div class="flex flex-wrap items-center justify-center gap-4 sm:gap-6 mt-5 text-xs text-base-content/50">
                <span class="flex items-center gap-1.5">
                    <svg class="w-3.5 h-3.5 text-green-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/></svg>
                    SSL-verschlüsselt
                </span>
                <span class="flex items-center gap-1.5">
                    <svg class="w-3.5 h-3.5 text-green-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                    DSGVO-konform
                </span>
                <span class="flex items-center gap-1.5">
                    <svg class="w-3.5 h-3.5 text-green-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                    Jederzeit bearbeitbar
                </span>
            </div>
        </div>

    @else
        {{-- Guest-View: Fullwidth mit Mini-Hero --}}
        <div class="eintragen-hero relative overflow-hidden">
            <div class="hero-blob hero-blob-1 pointer-events-none" aria-hidden="true"></div>
            <div class="hero-blob hero-blob-2 pointer-events-none" aria-hidden="true"></div>

            <div class="relative z-10 max-w-lg mx-auto px-4 sm:px-6 text-center">
                <div class="inline-flex items-center justify-center w-16 h-16 rounded-2xl mb-4"
                     style="background: rgba(255,255,255,0.15); backdrop-filter: blur(8px);">
                    <svg class="w-8 h-8 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/>
                    </svg>
                </div>
                <h1 class="text-2xl md:text-3xl font-bold text-white">Machen Sie Ihre Firma sichtbar</h1>
                <p class="text-white/70 mt-2 text-sm md:text-base">Kostenlos &middot; In 3 Minuten fertig</p>
            </div>
        </div>

        <div class="max-w-lg mx-auto px-4 sm:px-6 -mt-6 relative z-10 pb-8">
            <div class="wizard-card p-6 md:p-8">
                {{-- Benefits --}}
                <div class="space-y-5 mb-8">
                    <div class="flex items-start gap-4">
                        <div class="flex-shrink-0 w-10 h-10 rounded-xl flex items-center justify-center text-sm font-bold text-white"
                             style="background: var(--portal-primary, #3B82F6);">1</div>
                        <div>
                            <p class="font-semibold text-base-content">Profil erstellen</p>
                            <p class="text-sm text-base-content/50 mt-0.5">Name, Adresse, Kontaktdaten eintragen</p>
                        </div>
                    </div>
                    <div class="flex items-start gap-4">
                        <div class="flex-shrink-0 w-10 h-10 rounded-xl flex items-center justify-center text-sm font-bold text-white"
                             style="background: var(--portal-primary, #3B82F6);">2</div>
                        <div>
                            <p class="font-semibold text-base-content">Sichtbar werden</p>
                            <p class="text-sm text-base-content/50 mt-0.5">Kunden finden Sie in der Suche und bei Google</p>
                        </div>
                    </div>
                    <div class="flex items-start gap-4">
                        <div class="flex-shrink-0 w-10 h-10 rounded-xl flex items-center justify-center text-sm font-bold"
                             style="background: var(--portal-accent, #F59E0B); color: white;">
                            <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20"><path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/></svg>
                        </div>
                        <div>
                            <p class="font-semibold text-base-content">Vertrauen aufbauen</p>
                            <p class="text-sm text-base-content/50 mt-0.5">Bewertungen sammeln, Kunden überzeugen</p>
                        </div>
                    </div>
                </div>

                {{-- Social Proof --}}
                <div class="flex items-center gap-3 mb-8 pb-6 border-b border-base-200/60">
                    <div class="flex -space-x-2">
                        <div class="w-8 h-8 rounded-full border-2 border-white flex items-center justify-center text-xs font-bold text-white" style="background: var(--portal-primary, #3B82F6);">M</div>
                        <div class="w-8 h-8 rounded-full border-2 border-white flex items-center justify-center text-xs font-bold text-white" style="background: var(--portal-secondary, #1E40AF);">S</div>
                        <div class="w-8 h-8 rounded-full border-2 border-white flex items-center justify-center text-xs font-bold text-white" style="background: var(--portal-accent, #F59E0B);">K</div>
                    </div>
                    <p class="text-sm text-base-content/60">
                        Bereits <span class="font-semibold text-base-content">tausende Unternehmen</span> eingetragen.
                    </p>
                </div>

                {{-- CTAs --}}
                <div class="space-y-3">
                    <a href="{{ route('register') }}?intended={{ urlencode(route('portal.companies.create')) }}"
                       class="btn-portal w-full flex items-center justify-center gap-2 py-3 text-center rounded-xl font-semibold"
                       style="border-radius: 0.75rem;">
                        Kostenlos registrieren
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 8l4 4m0 0l-4 4m4-4H3"/></svg>
                    </a>
                    <a href="{{ route('login') }}?intended={{ urlencode(route('portal.companies.create')) }}"
                       class="btn-portal-outline w-full flex items-center justify-center py-3 text-center rounded-xl"
                       style="border-radius: 0.75rem;">
                        Bereits registriert? Anmelden
                    </a>
                </div>
            </div>

            {{-- Trust-Bar --}}
            <div class="flex flex-wrap items-center justify-center gap-4 sm:gap-6 mt-5 text-xs text-base-content/50">
                <span class="flex items-center gap-1.5">
                    <svg class="w-3.5 h-3.5 text-green-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                    Dauerhaft kostenlos
                </span>
                <span class="flex items-center gap-1.5">
                    <svg class="w-3.5 h-3.5 text-green-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                    In 3 Minuten fertig
                </span>
                <span class="flex items-center gap-1.5">
                    <svg class="w-3.5 h-3.5 text-green-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                    Jederzeit bearbeitbar
                </span>
            </div>
        </div>
    @endauth

</div>

@endsection

@push('styles')
<style>
    @keyframes draw-icon {
        to { stroke-dashoffset: 0; }
    }
</style>
@endpush
