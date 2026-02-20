@extends('layouts.verwaltung')

@section('title', $team->name . ' bearbeiten — Verwaltung')

@section('content')
    <div class="mb-6">
        <h1 class="text-xl sm:text-2xl font-bold text-base-content">{{ $team->name }}</h1>
        <p class="text-sm text-base-content/60 mt-1">Team bearbeiten und Mitglieder verwalten</p>
    </div>

    @livewire('verwaltung.team-form', ['team' => $team])
@endsection
