@extends('layouts.verwaltung')

@section('title', 'Theme & Branding — Verwaltung')

@section('content')
    {{-- Page Header --}}
    <div class="dash-page-header">
        <div>
            <h1 class="dash-page-title">Theme & Branding</h1>
            <p class="dash-page-subtitle">Design, Farben, Logo und Schriftarten Ihres Portals anpassen</p>
        </div>
    </div>

    {{-- Settings Tabs Navigation --}}
    <div class="dash-tab-bar" style="margin-bottom:1.5rem;">
        <a href="{{ route('verwaltung.settings.general') }}" class="dash-tab">Allgemein</a>
        <a href="{{ route('verwaltung.settings.theme') }}" class="dash-tab dash-tab-active">Theme & Branding</a>
        <a href="{{ route('verwaltung.settings.legal') }}" class="dash-tab">Rechtliches</a>
    </div>

    {{-- Theme Settings Form (Livewire) --}}
    @livewire('verwaltung.theme-settings-form')
@endsection
