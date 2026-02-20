@extends('layouts.verwaltung')

@section('title', 'Neue Stadt — Verwaltung')

@section('content')
    <div class="mb-6">
        <h1 class="text-xl sm:text-2xl font-bold text-base-content">Neue Stadt erstellen</h1>
        <p class="text-sm text-base-content/60 mt-1">Stadt oder Gemeinde für das Verzeichnis anlegen</p>
    </div>

    @livewire('verwaltung.city-form')
@endsection
