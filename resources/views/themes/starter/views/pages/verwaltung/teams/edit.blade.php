@extends('layouts.verwaltung')

@section('title', $team->name . ' bearbeiten — Verwaltung')

@section('content')
    <div class="dash-page-header">
        <div>
            <h1 class="dash-page-title">{{ $team->name }}</h1>
            <p class="dash-page-subtitle">Team bearbeiten und Mitglieder verwalten</p>
        </div>
    </div>

    @livewire('verwaltung.team-form', ['team' => $team])
@endsection
