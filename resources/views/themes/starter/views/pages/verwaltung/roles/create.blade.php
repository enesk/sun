@extends('layouts.verwaltung')

@section('title', 'Neue Rolle — Verwaltung')

@section('content')
    <div class="dash-page-header">
        <div>
            <h1 class="dash-page-title">Neue Rolle erstellen</h1>
            <p class="dash-page-subtitle">Berechtigungsrolle mit Zugriffsrechten definieren</p>
        </div>
    </div>

    @livewire('verwaltung.role-form')
@endsection
