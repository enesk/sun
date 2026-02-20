@extends('layouts.verwaltung')

@section('title', 'Kategorien verwalten — Verwaltung')

@section('content')
    {{-- Page Header --}}
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 mb-6">
        <div>
            <h1 class="text-xl sm:text-2xl font-bold text-base-content">Kategorien</h1>
            <p class="text-sm text-base-content/60 mt-1">Kategorien für das Branchenverzeichnis verwalten</p>
        </div>
        <a href="{{ route('verwaltung.categories.create') }}"
           class="inline-flex items-center gap-2 px-4 py-2.5 rounded-lg text-white text-sm font-medium transition-all hover:opacity-90 shadow-sm"
           style="background-color: var(--portal-primary, #3b82f6);">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15"/>
            </svg>
            Neue Kategorie
        </a>
    </div>

    {{-- Category Table (Livewire) --}}
    @livewire('verwaltung.category-table')
@endsection
