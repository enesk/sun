@extends('layouts.verwaltung')

@section('title', 'Rechtliches — Verwaltung')

@section('content')
    {{-- Page Header --}}
    <div class="dash-page-header">
        <div>
            <h1 class="dash-page-title">Rechtliches</h1>
            <p class="dash-page-subtitle">Impressum und Datenschutzerklärung für Ihr Portal pflegen</p>
        </div>
    </div>

    {{-- Settings Tabs Navigation --}}
    <div class="dash-tab-bar" style="margin-bottom:1.5rem;">
        <a href="{{ route('verwaltung.settings.general') }}" class="dash-tab">Allgemein</a>
        <a href="{{ route('verwaltung.settings.theme') }}" class="dash-tab">Theme & Branding</a>
        <a href="{{ route('verwaltung.settings.legal') }}" class="dash-tab dash-tab-active">Rechtliches</a>
    </div>

    {{-- Legal Settings Form (Livewire) --}}
    @livewire('verwaltung.legal-settings-form')
@endsection
