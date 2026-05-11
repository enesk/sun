@extends('layouts.verwaltung')

@section('title', 'Neues Team — Verwaltung')

@section('content')
    <div class="dash-page-header">
        <div>
            <h1 class="dash-page-title">Neues Team erstellen</h1>
            <p class="dash-page-subtitle">Team anlegen und Mitglieder zuweisen</p>
        </div>
    </div>

    @livewire('verwaltung.team-form')
@endsection
