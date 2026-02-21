@extends('layouts.verwaltung')

@section('title', 'Neue Kategorie — Verwaltung')

@section('content')
    <div class="dash-page-header">
        <div>
            <h1 class="dash-page-title">Neue Kategorie erstellen</h1>
            <p class="dash-page-subtitle">Kategorie für das Branchenverzeichnis anlegen</p>
        </div>
    </div>

    @livewire('verwaltung.category-form')
@endsection
