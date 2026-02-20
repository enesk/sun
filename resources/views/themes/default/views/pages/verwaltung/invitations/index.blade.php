@extends('layouts.verwaltung')

@section('title', 'Einladungen — Verwaltung')

@section('content')
    {{-- Page Header --}}
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 mb-6">
        <div>
            <h1 class="text-xl sm:text-2xl font-bold text-base-content">Einladungen</h1>
            <p class="text-sm text-base-content/60 mt-1">Offene und versendete Einladungen verwalten</p>
        </div>
        <a href="{{ route('verwaltung.invitations.create') }}"
           class="inline-flex items-center gap-2 px-4 py-2.5 rounded-lg text-white text-sm font-medium transition-all hover:opacity-90 shadow-sm"
           style="background-color: var(--portal-primary, #3b82f6);">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" d="M21.75 6.75v10.5a2.25 2.25 0 01-2.25 2.25h-15a2.25 2.25 0 01-2.25-2.25V6.75m19.5 0A2.25 2.25 0 0019.5 4.5h-15a2.25 2.25 0 00-2.25 2.25m19.5 0v.243a2.25 2.25 0 01-1.07 1.916l-7.5 4.615a2.25 2.25 0 01-2.36 0L3.32 8.91a2.25 2.25 0 01-1.07-1.916V6.75"/>
            </svg>
            Neue Einladung
        </a>
    </div>

    {{-- Invitation Table (Livewire) --}}
    @livewire('verwaltung.invitation-table')
@endsection
