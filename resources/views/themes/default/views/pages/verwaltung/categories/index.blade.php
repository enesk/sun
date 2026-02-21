@extends('layouts.verwaltung')

@section('title', 'Kategorien verwalten — Verwaltung')

@section('content')
    {{-- Page Header --}}
    <div class="dash-page-header">
        <div>
            <h1 class="dash-page-title">Kategorien</h1>
            <p class="dash-page-subtitle">Kategorien für das Branchenverzeichnis verwalten</p>
        </div>
        <a href="{{ route('verwaltung.categories.create') }}" class="dash-btn dash-btn-primary">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15"/>
            </svg>
            Neue Kategorie
        </a>
    </div>

    {{-- Category Table (Livewire) --}}
    @livewire('verwaltung.category-table')
@endsection
