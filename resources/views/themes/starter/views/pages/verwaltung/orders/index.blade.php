@extends('layouts.verwaltung')

@section('title', 'Bestellungen — Verwaltung')

@section('content')
    {{-- Page Header --}}
    <div class="dash-page-header">
        <div>
            <h1 class="dash-page-title">Bestellungen</h1>
            <p class="dash-page-subtitle">Einmalkäufe und Bestellhistorie</p>
        </div>
    </div>

    {{-- Order Table (Livewire) --}}
    @livewire('verwaltung.order-table')
@endsection
