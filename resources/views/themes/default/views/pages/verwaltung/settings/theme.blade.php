@extends('layouts.verwaltung')

@section('title', 'Theme & Branding — Verwaltung')

@section('content')
    {{-- Page Header --}}
    <div class="mb-6">
        <h1 class="text-xl sm:text-2xl font-bold text-base-content">Theme & Branding</h1>
        <p class="text-sm text-base-content/60 mt-1">Design, Farben, Logo und Schriftarten Ihres Portals anpassen</p>
    </div>

    {{-- Settings Tabs Navigation --}}
    <div class="flex gap-1 mb-6 border-b border-base-200 overflow-x-auto">
        <a href="{{ route('verwaltung.settings.general') }}"
           class="px-4 py-2.5 text-sm font-medium border-b-2 border-transparent text-base-content/60 hover:text-base-content transition-colors whitespace-nowrap">
            Allgemein
        </a>
        <a href="{{ route('verwaltung.settings.theme') }}"
           class="px-4 py-2.5 text-sm font-medium border-b-2 transition-colors whitespace-nowrap"
           style="border-color: var(--portal-primary, #3b82f6); color: var(--portal-primary, #3b82f6);">
            Theme & Branding
        </a>
        <a href="{{ route('verwaltung.settings.legal') }}"
           class="px-4 py-2.5 text-sm font-medium border-b-2 border-transparent text-base-content/60 hover:text-base-content transition-colors whitespace-nowrap">
            Rechtliches
        </a>
    </div>

    {{-- Theme Settings Form (Livewire) --}}
    @livewire('verwaltung.theme-settings-form')
@endsection
