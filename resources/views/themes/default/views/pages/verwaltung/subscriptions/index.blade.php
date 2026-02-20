@extends('layouts.verwaltung')

@section('title', 'Abonnements — Verwaltung')

@section('content')
    {{-- Page Header --}}
    <div class="mb-6">
        <h1 class="text-xl sm:text-2xl font-bold text-base-content">Abonnements</h1>
        <p class="text-sm text-base-content/60 mt-1">Aktive Abonnements, Pläne und Verlängerungen verwalten</p>
    </div>

    {{-- Subscription List (Livewire) --}}
    @livewire('verwaltung.subscription-list')
@endsection
