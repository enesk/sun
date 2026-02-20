@extends('layouts.verwaltung')

@section('title', 'Bewertungen moderieren — Verwaltung')

@section('content')
    {{-- Page Header --}}
    <div class="mb-6">
        <h1 class="text-xl sm:text-2xl font-bold text-base-content">Bewertungen</h1>
        <p class="text-sm text-base-content/60 mt-1">Bewertungen prüfen, freigeben und moderieren</p>
    </div>

    {{-- Review Table (Livewire) --}}
    @livewire('verwaltung.review-table')
@endsection
