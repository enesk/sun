@extends('layouts.verwaltung')

@section('title', 'Neue Einladung — Verwaltung')

@section('content')
    <div class="mb-6">
        <h1 class="text-xl sm:text-2xl font-bold text-base-content">Neue Einladung versenden</h1>
        <p class="text-sm text-base-content/60 mt-1">Mitglied per E-Mail zum Workspace einladen</p>
    </div>

    @livewire('verwaltung.invitation-form')
@endsection
