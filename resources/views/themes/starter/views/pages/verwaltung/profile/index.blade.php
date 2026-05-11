@extends('layouts.verwaltung')

@section('title', 'Profil — Verwaltung')

@section('content')
    {{-- Page Header --}}
    <div class="dash-page-header">
        <div>
            <h1 class="dash-page-title">Mein Profil</h1>
            <p class="dash-page-subtitle">Persönliche Daten, Passwort und Sicherheitseinstellungen</p>
        </div>
    </div>

    {{-- Profile Form (Livewire) --}}
    @livewire('verwaltung.profile-form')
@endsection
