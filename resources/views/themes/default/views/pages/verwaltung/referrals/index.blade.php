@extends('layouts.verwaltung')

@section('title', 'Empfehlungen — Verwaltung')

@section('content')
    {{-- Page Header --}}
    <div class="mb-6">
        <h1 class="text-xl sm:text-2xl font-bold text-base-content">Empfehlungen</h1>
        <p class="text-sm text-base-content/60 mt-1">Empfehlen Sie das Portal weiter und verdienen Sie Belohnungen</p>
    </div>

    {{-- Referral Dashboard (Livewire) --}}
    @livewire('verwaltung.referral-dashboard')
@endsection
