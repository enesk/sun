@extends('layouts.verwaltung')

@section('title', 'Zahlungen — Verwaltung')

@section('content')
    {{-- Page Header --}}
    <div class="mb-6">
        <h1 class="text-xl sm:text-2xl font-bold text-base-content">Zahlungen</h1>
        <p class="text-sm text-base-content/60 mt-1">Transaktionshistorie und Rechnungen</p>
    </div>

    {{-- Transaction Table (Livewire) --}}
    @livewire('verwaltung.transaction-table')
@endsection
