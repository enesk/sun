@extends('layouts.verwaltung')

@section('title', 'Neue Rolle — Verwaltung')

@section('content')
    <div class="mb-6">
        <h1 class="text-xl sm:text-2xl font-bold text-base-content">Neue Rolle erstellen</h1>
        <p class="text-sm text-base-content/60 mt-1">Berechtigungsrolle mit Zugriffsrechten definieren</p>
    </div>

    @livewire('verwaltung.role-form')
@endsection
