@extends('layouts.verwaltung')

@section('title', 'Allgemeine Einstellungen — Verwaltung')

@section('content')
    {{-- Page Header --}}
    <div class="dash-page-header">
        <div>
            <h1 class="dash-page-title">Allgemeine Einstellungen</h1>
            <p class="dash-page-subtitle">Workspace, Kontakt, SEO und Portal-Funktionen konfigurieren</p>
        </div>
    </div>

    {{-- Settings Tabs Navigation --}}
    <div class="dash-tab-bar" style="margin-bottom:1.5rem;">
        <a href="{{ route('verwaltung.settings.general') }}" class="dash-tab dash-tab-active">Allgemein</a>
        <a href="{{ route('verwaltung.settings.theme') }}" class="dash-tab">Theme & Branding</a>
        <a href="{{ route('verwaltung.settings.legal') }}" class="dash-tab">Rechtliches</a>
    </div>

    {{-- General Settings Form (Livewire) --}}
    @livewire('verwaltung.general-settings-form')
@endsection
