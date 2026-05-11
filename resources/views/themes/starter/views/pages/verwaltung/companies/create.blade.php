@extends('layouts.verwaltung')

@section('title', 'Neue Firma — Verwaltung')

@section('content')
    <div class="dash-page-header">
        <div>
            <h1 class="dash-page-title">Neue Firma erstellen</h1>
            <p class="dash-page-subtitle">Füllen Sie die Firmendaten aus</p>
        </div>
    </div>

    @livewire('verwaltung.company-form')
@endsection
