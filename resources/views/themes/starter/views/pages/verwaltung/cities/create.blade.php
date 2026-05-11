@extends('layouts.verwaltung')

@section('title', 'Neue Stadt — Verwaltung')

@section('content')
    <div class="dash-page-header">
        <div>
            <h1 class="dash-page-title">Neue Stadt erstellen</h1>
            <p class="dash-page-subtitle">Stadt oder Gemeinde für das Verzeichnis anlegen</p>
        </div>
    </div>

    @livewire('verwaltung.city-form')
@endsection
