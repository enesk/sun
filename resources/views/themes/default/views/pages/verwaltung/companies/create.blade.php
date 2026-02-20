@extends('layouts.verwaltung')

@section('title', 'Neue Firma — Verwaltung')

@section('content')
    <div class="mb-6">
        <h1 class="text-xl sm:text-2xl font-bold text-base-content">Neue Firma erstellen</h1>
        <p class="text-sm text-base-content/60 mt-1">Füllen Sie die Firmendaten aus</p>
    </div>

    @livewire('verwaltung.company-form')
@endsection
