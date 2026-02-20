@extends('layouts.verwaltung')

@section('title', 'Profil — Verwaltung')

@section('content')
    {{-- Page Header --}}
    <div class="mb-6">
        <h1 class="text-xl sm:text-2xl font-bold text-base-content">Mein Profil</h1>
        <p class="text-sm text-base-content/60 mt-1">Persönliche Daten, Passwort und Sicherheitseinstellungen</p>
    </div>

    {{-- Profile Form (Livewire) --}}
    @livewire('verwaltung.profile-form')
@endsection
