@extends('layouts.verwaltung')

@section('title', 'Neue Kategorie — Verwaltung')

@section('content')
    <div class="mb-6">
        <h1 class="text-xl sm:text-2xl font-bold text-base-content">Neue Kategorie erstellen</h1>
        <p class="text-sm text-base-content/60 mt-1">Kategorie für das Branchenverzeichnis anlegen</p>
    </div>

    @livewire('verwaltung.category-form')
@endsection
