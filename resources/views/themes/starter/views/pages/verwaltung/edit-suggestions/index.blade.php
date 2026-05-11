@extends('layouts.verwaltung')

@section('title', 'Änderungsvorschläge — Verwaltung')

@section('content')
    {{-- Page Header --}}
    <div class="dash-page-header">
        <div>
            <h1 class="dash-page-title">Änderungsvorschläge</h1>
            <p class="dash-page-subtitle">Von Besuchern vorgeschlagene Änderungen an Firmeneinträgen prüfen und moderieren</p>
        </div>
    </div>

    {{-- Edit Suggestions Table (Livewire) --}}
    @livewire('verwaltung.edit-suggestions-table')
@endsection
