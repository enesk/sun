@extends('layouts.verwaltung')

@section('title', 'Bewertungen moderieren — Verwaltung')

@section('content')
    {{-- Page Header --}}
    <div class="dash-page-header">
        <div>
            <h1 class="dash-page-title">Bewertungen</h1>
            <p class="dash-page-subtitle">Bewertungen prüfen, freigeben und moderieren</p>
        </div>
    </div>

    {{-- Review Table (Livewire) --}}
    @livewire('verwaltung.review-table')
@endsection
