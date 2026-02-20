@extends('layouts.verwaltung')

@section('title', 'Neues Team — Verwaltung')

@section('content')
    <div class="mb-6">
        <h1 class="text-xl sm:text-2xl font-bold text-base-content">Neues Team erstellen</h1>
        <p class="text-sm text-base-content/60 mt-1">Team anlegen und Mitglieder zuweisen</p>
    </div>

    @livewire('verwaltung.team-form')
@endsection
