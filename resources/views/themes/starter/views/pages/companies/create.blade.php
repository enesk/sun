@extends('layouts.app')

@section('title', 'Firma eintragen — ' . ($currentTenant->name ?? config('app.name')))
@section('meta_description', 'Tragen Sie Ihr Unternehmen kostenlos ein und werden Sie von Kunden gefunden.')

@section('content')

<div class="eintragen-page">

    {{-- Mini-Hero-Banner mit Mesh-Gradient (immer sichtbar) --}}
    <div class="eintragen-hero relative overflow-hidden">
        <div class="hero-blob hero-blob-1 pointer-events-none" aria-hidden="true"></div>
        <div class="hero-blob hero-blob-2 pointer-events-none" aria-hidden="true"></div>

        <div class="relative z-10 max-w-3xl mx-auto px-4 sm:px-6 text-center">
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

    {{-- Glass-Card mit Wizard (immer sichtbar, bei Guests mit Blur + Overlay) --}}
    <div class="max-w-3xl mx-auto px-4 sm:px-6 -mt-6 relative z-10 pb-8">
        <div class="relative">
            {{-- Wizard Glass Card --}}
            <div class="wizard-card @guest select-none @endguest" @guest style="filter: blur(3px); pointer-events: none;" @endguest aria-hidden="{{ auth()->guest() ? 'true' : 'false' }}">
                @auth
                    <livewire:portal.company-registration-wizard />
                @else
                    {{-- Statischer Wizard-Vorschau für Gäste --}}
                    <div class="px-5 sm:px-7 pt-5 pb-0">
                        <div class="flex items-center justify-between">
                            @foreach([1 => 'Firmendaten', 2 => 'Adresse', 3 => 'Kontakt', 4 => 'Logo', 5 => 'Fertig'] as $n => $label)
                                <div class="flex items-center {{ $n < 5 ? 'flex-1' : '' }}">
                                    <div class="flex items-center gap-2">
                                        <div class="flex items-center justify-center w-9 h-9 rounded-full {{ $n === 1 ? 'bg-portal-primary text-white shadow-md ring-2 ring-portal-primary/20' : 'bg-base-200/50 text-base-content/30' }}">
                                            <span class="text-xs font-bold">{{ $n }}</span>
                                        </div>
                                        <span class="text-xs whitespace-nowrap {{ $n === 1 ? 'text-portal-primary font-semibold' : 'text-base-content/30' }}">{{ $label }}</span>
                                    </div>
                                    @if($n < 5)
                                        <div class="flex-1 mx-3"><div class="h-[2px] bg-base-200 rounded-full"></div></div>
                                    @endif
                                </div>
                            @endforeach
                        </div>
                    </div>
                    <div class="p-5 sm:p-7">
                        <div class="form-section-header"><h2>Firmendaten</h2><p>Grundlegende Informationen zu Ihrem Unternehmen.</p></div>
                        <div class="space-y-6">
                            <div>
                                <label class="label-portal">Firmenname <span class="required">*</span></label>
                                <div class="input-portal h-11"></div>
                            </div>
                            <div>
                                <label class="label-portal">Beschreibung</label>
                                <div class="textarea-portal h-24"></div>
                            </div>
                            <div>
                                <label class="label-portal">Kategorien <span class="required">*</span></label>
                                <div class="flex flex-wrap gap-2 mt-2">
                                    @foreach(['Handwerk', 'Gastronomie', 'Gesundheit', 'Dienstleistungen'] as $cat)
                                        <span class="inline-flex items-center px-3 py-2 rounded-lg text-sm font-medium border bg-white/60 text-base-content/50 border-base-300/50">{{ $cat }}</span>
                                    @endforeach
                                </div>
                            </div>
                        </div>
                    </div>
                @endauth
            </div>

            @guest
                {{-- Auth-Gate Overlay Modal --}}
                <div class="absolute inset-0 z-20 flex items-center justify-center" x-data="{ tab: 'register' }">
                    {{-- Hintergrund-Dimmer --}}
                    <div class="absolute inset-0 bg-white/30 backdrop-blur-sm rounded-2xl"></div>

                    {{-- Modal-Card --}}
                    <div class="relative z-10 w-full max-w-sm mx-4 wizard-card p-6 md:p-8 shadow-2xl">
                        {{-- Schließen-Hinweis --}}
                        <div class="text-center mb-6">
                            <div class="inline-flex items-center justify-center w-12 h-12 rounded-2xl mb-3"
                                 style="background: rgba(var(--portal-primary-rgb, 59, 130, 246), 0.1);">
                                <svg class="w-6 h-6 text-portal-primary" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
                                </svg>
                            </div>
                            <h2 class="text-lg font-bold text-base-content">Anmeldung erforderlich</h2>
                            <p class="text-sm text-base-content/50 mt-1">Erstellen Sie ein Konto oder melden Sie sich an, um Ihre Firma einzutragen.</p>
                        </div>

                        {{-- Benefits kompakt --}}
                        <div class="space-y-2.5 mb-6">
                            <div class="flex items-center gap-2.5 text-sm">
                                <svg class="w-4 h-4 text-green-500 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                                <span class="text-base-content/70">Dauerhaft kostenlos</span>
                            </div>
                            <div class="flex items-center gap-2.5 text-sm">
                                <svg class="w-4 h-4 text-green-500 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                                <span class="text-base-content/70">In 3 Minuten fertig</span>
                            </div>
                            <div class="flex items-center gap-2.5 text-sm">
                                <svg class="w-4 h-4 text-green-500 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                                <span class="text-base-content/70">Jederzeit bearbeitbar</span>
                            </div>
                        </div>

                        {{-- Social Proof --}}
                        <div class="flex items-center gap-2.5 mb-6 pb-5 border-b border-base-200/60">
                            <div class="flex -space-x-2">
                                <div class="w-7 h-7 rounded-full border-2 border-white flex items-center justify-center text-xs font-bold text-white" style="background: var(--portal-primary, #3B82F6);">M</div>
                                <div class="w-7 h-7 rounded-full border-2 border-white flex items-center justify-center text-xs font-bold text-white" style="background: var(--portal-secondary, #1E40AF);">S</div>
                                <div class="w-7 h-7 rounded-full border-2 border-white flex items-center justify-center text-xs font-bold text-white" style="background: var(--portal-accent, #F59E0B);">K</div>
                            </div>
                            <p class="text-xs text-base-content/50">
                                <span class="font-semibold text-base-content/70">Tausende Unternehmen</span> vertrauen uns.
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
                </div>
            @endguest
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

</div>

@endsection

@push('styles')
<style>
    @keyframes draw-icon {
        to { stroke-dashoffset: 0; }
    }
</style>
@endpush
