@extends('layouts.verwaltung')

@section('title', 'Neue Einladung — Verwaltung')

@section('content')
    <div class="dash-page-header">
        <div>
            <h1 class="dash-page-title">Neue Einladung versenden</h1>
            <p class="dash-page-subtitle">Mitglied per E-Mail zum Workspace einladen</p>
        </div>
    </div>

    @livewire('verwaltung.invitation-form')
@endsection
