@extends('layouts.verwaltung')

@section('title', 'Zahlungen — Verwaltung')

@section('content')
    {{-- Page Header --}}
    <div class="dash-page-header">
        <div>
            <h1 class="dash-page-title">Zahlungen</h1>
            <p class="dash-page-subtitle">Transaktionshistorie und Rechnungen</p>
        </div>
    </div>

    {{-- Transaction Table (Livewire) --}}
    @livewire('verwaltung.transaction-table')
@endsection
