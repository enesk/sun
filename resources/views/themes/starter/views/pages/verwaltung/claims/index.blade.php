@extends('layouts.verwaltung')

@section('title', 'Claim-Anträge — Verwaltung')

@section('content')
    {{-- Page Header --}}
    <div class="dash-page-header">
        <div>
            <h1 class="dash-page-title">Claim-Anträge</h1>
            <p class="dash-page-subtitle">Firmen-Übernahmen prüfen, Dokumente verifizieren und freigeben</p>
        </div>
    </div>

    {{-- Claim Table (Livewire) --}}
    @livewire('verwaltung.claim-table')
@endsection
