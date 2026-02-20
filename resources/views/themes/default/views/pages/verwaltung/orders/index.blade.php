@extends('layouts.verwaltung')

@section('title', 'Bestellungen — Verwaltung')

@section('content')
    {{-- Page Header --}}
    <div class="mb-6">
        <h1 class="text-xl sm:text-2xl font-bold text-base-content">Bestellungen</h1>
        <p class="text-sm text-base-content/60 mt-1">Einmalkäufe und Bestellhistorie</p>
    </div>

    {{-- Order Table (Livewire) --}}
    @livewire('verwaltung.order-table')
@endsection
