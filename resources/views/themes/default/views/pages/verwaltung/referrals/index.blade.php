@extends('layouts.verwaltung')

@section('title', 'Empfehlungen — Verwaltung')

@section('content')
    {{-- Page Header --}}
    <div class="dash-page-header">
        <div>
            <h1 class="dash-page-title">Empfehlungen</h1>
            <p class="dash-page-subtitle">Empfehlen Sie das Portal weiter und verdienen Sie Belohnungen</p>
        </div>
    </div>

    {{-- Referral Dashboard (Livewire) --}}
    @livewire('verwaltung.referral-dashboard')
@endsection
